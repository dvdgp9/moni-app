<?php
/** Run: php tests/declarations_http.php (requires pdo_sqlite and proc_open). */
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = 0;
function verify(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) { throw new RuntimeException($message); }
}
function request(string $url, array &$cookies, ?array $form = null): array
{
    $headers = ['Cookie: ' . implode('; ', $cookies)];
    if ($form !== null) { $headers[] = 'Content-Type: application/x-www-form-urlencoded'; }
    $context = stream_context_create(['http' => [
        'method' => $form === null ? 'GET' : 'POST',
        'header' => implode("\r\n", $headers),
        'content' => $form === null ? '' : http_build_query($form),
        'follow_location' => 0,
        'ignore_errors' => true,
        'timeout' => 10,
    ]]);
    $body = file_get_contents($url, false, $context);
    if ($body === false) { throw new RuntimeException('HTTP request failed'); }
    $status = 0;
    $location = null;
    foreach ($http_response_header as $header) {
        if (preg_match('/^HTTP\/\S+ (\d+)/', $header, $m)) { $status = (int)$m[1]; }
        if (stripos($header, 'Location: ') === 0) { $location = substr($header, 10); }
        if (preg_match('/^Set-Cookie: ([^=]+)=([^;]*)/i', $header, $m)) { $cookies[$m[1]] = $m[1] . '=' . $m[2]; }
    }
    return ['status' => $status, 'location' => $location, 'body' => $body];
}
function token(string $html): string
{
    if (!preg_match('/name="_token" value="([^"]+)"/', $html, $m)) { throw new RuntimeException('Missing CSRF token'); }
    return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
}

foreach ([4096, 0] as $bufferSize) {
    $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$listener) { throw new RuntimeException($error); }
    $address = stream_socket_get_name($listener, false);
    fclose($listener);
    $base = 'http://' . $address;
    $temp = sys_get_temp_dir() . '/moni-http-' . bin2hex(random_bytes(6));
    mkdir($temp, 0700);
    $env = getenv();
    $env['MONI_HTTP_TEST'] = '1';
    $process = proc_open([
        PHP_BINARY, '-d', 'output_buffering=' . $bufferSize,
        '-d', 'session.save_path=' . $temp,
        '-S', $address, '-t', $root . '/public', $root . '/tests/fixtures/declarations_router.php',
    ], [0 => ['pipe', 'r'], 1 => ['file', $temp . '/server.log', 'a'], 2 => ['file', $temp . '/server.log', 'a']], $pipes, $root, $env);
    if (!is_resource($process)) { throw new RuntimeException('Could not start test server'); }
    fclose($pipes[0]);
    try {
        $ready = false;
        for ($i = 0; $i < 100; $i++) {
            $socket = @stream_socket_client('tcp://' . $address, $errno, $error, .05);
            if ($socket) { fclose($socket); $ready = true; break; }
            usleep(20000);
        }
        verify($ready, 'Test server starts');
        foreach (['303', '130'] as $model) {
            $cookies = [];
            $path = '/declaraciones?year=2026&quarter=3&model=' . $model;
            $get = request($base . $path, $cookies);
            verify($get['status'] === 200, 'Declarations GET succeeds');
            verify(substr_count($get['body'], 'data-tax-center data-model=') === 1, 'Layout renders the declaration once');
            verify(str_contains($get['body'], '</html>'), 'Complete layout is rendered');
            $csrf = token($get['body']);
            $post = static function (array $form, string $anchor, string $message) use ($base, $path, $csrf, &$cookies): string {
                $response = request($base . $path, $cookies, ['_token' => $csrf] + $form);
                verify($response['status'] === 303, 'Successful action redirects with 303: ' . $form['tax_action']);
                verify($response['location'] === $path . $anchor, 'Redirect preserves model, year, quarter and anchor');
                verify($response['body'] === '', 'Redirect contains no partial layout or fallback page');
                // Follow with GET, as the browser must do after a 303, including on an anchored URL.
                $get = request($base . $path, $cookies);
                verify($get['status'] === 200 && str_contains($get['body'], 'data-tax-center'), 'Redirect target renders declarations');
                verify(str_contains($get['body'], $message), 'Saved action is visible after redirect');
                verify(!str_contains($get['body'], 'Redirigiendo'), 'No stuck redirect page');
                return $get['body'];
            };
            $post(['tax_action' => 'setup', 'tax_models' => ['303', '130'], 'activity_mode' => 'professional'], '', 'Configuración fiscal guardada.');
            $saved = $post([
                'tax_action' => 'save', 'review_records' => '1', 'review_special' => '1',
                'no_previous' => '1', 'previous_net' => '20000',
                'boxes' => $model === '303' ? ['07' => '1234,56'] : ['01' => '1234,56'],
            ], '#casillas', 'Borrador guardado y cálculo actualizado.');
            verify(str_contains($saved, 'value="1234,56"'), 'Manual amount persists');
            preg_match('/name="signature" value="([^"]+)"/', $saved, $signature);
            verify(isset($signature[1]), 'Review signature is available');
            $post(['tax_action' => 'review', 'signature' => $signature[1]], '#casillas', 'Declaración marcada como revisada.');
            $filed = $post([
                'tax_action' => 'presented', 'signature' => $signature[1],
                'receipt' => $model . '0000000042', 'filed_date' => date('Y-m-d'), 'confirm_presented' => '1',
            ], '#historial', 'Presentación registrada.');
            verify(substr_count($filed, 'class="tax-history-entry"') === 1, 'Presentation is stored once');
            $historyBoxes = $model === '303' ? ['71' => '10', '87' => '0', '72' => '0'] : ['07' => '10', '15' => '0', '16' => '0', '19' => '10'];
            $history = $post([
                'tax_action' => 'import_history', 'history_model' => $model, 'history_year' => '2026',
                'history_quarter' => '2', 'history_receipt' => $model . '0000000002',
                'history_date' => '2026-07-10', 'history_boxes' => [$model => $historyBoxes],
            ], '#historial', 'Declaración anterior registrada.');
            verify(substr_count($history, 'class="tax-history-entry"') === 2, 'Imported history is stored once');
            $invalid = request($base . $path, $cookies, ['tax_action' => 'save', '_token' => 'invalid']);
            verify($invalid['status'] === 200 && str_contains($invalid['body'], 'sesión del formulario ha caducado'), 'Invalid CSRF stays on the form');
            verify($invalid['location'] === null, 'Failed validation does not redirect');
            $malformed = request($base . $path, $cookies, ['tax_action' => 'save', '_token' => $csrf, 'boxes' => ['01' => 'bad', '07' => 'bad']]);
            verify($malformed['status'] === 200 && str_contains($malformed['body'], 'importe válido'), 'Invalid amount stays on the form');
            verify(str_contains($malformed['body'], 'value="bad"'), 'Failed save preserves entered amounts');
        }
    } finally {
        proc_terminate($process);
        proc_close($process);
        foreach (glob($temp . '/*') as $file) { unlink($file); }
        rmdir($temp);
    }
}
echo 'OK: ' . $checks . " HTTP checks (303 and 130; output_buffering=4096 and 0).\n";
