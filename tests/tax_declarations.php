<?php
/** Run: php tests/tax_declarations.php (no database or production data required). */
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use Moni\Services\TaxDeclarationService as Tax;
use Moni\Support\TaxAmount;

$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    $checks++;
    if (!$condition) { throw new RuntimeException($message); }
}
function equal($actual, $expected, string $message): void {
    check(is_float($expected) ? abs((float)$actual - $expected) < .001 : $actual === $expected, $message . ': ' . json_encode($actual));
}
function record(string $model, int $year, int $quarter, array $boxes, string $stamp = '2026-10-06 10:00:00'): array {
    return ['model'=>$model,'year'=>$year,'quarter'=>$quarter,'boxes'=>$boxes,'status'=>'presented','saved_at'=>$stamp];
}
$review = ['review_records'=>true,'review_special'=>true,'no_previous'=>true,'previous_net'=>20000.0];
foreach (['1.234,56'=>1234.56,'1234,56'=>1234.56,'1234.56'=>1234.56,'1.234'=>1234.0,'-28,43'=>-28.43,'0'=>0.0,'1.234.567,89'=>1234567.89] as $text=>$value) {
    equal(TaxAmount::parse((string)$text),$value,'Spanish amounts');
}
foreach (['12foo','1,234.56','1.234,567','1e3','NaN','--1','','1,2,3'] as $text) {
    $failed = false;
    try { TaxAmount::parse($text); } catch (InvalidArgumentException $e) { $failed = true; }
    check($failed,'Malformed amount rejected: ' . $text);
}
equal(TaxAmount::copy(3445.0),'3445,00','Clipboard strips grouping and currency');
equal(Tax::defaultPeriod(new DateTimeImmutable('2026-10-06')),[2026,3],'October opens Q3');
equal(Tax::defaultPeriod(new DateTimeImmutable('2026-01-06')),[2025,4],'January opens last year');
equal(Tax::defaultPeriod(new DateTimeImmutable('2026-04-01')),[2026,1],'April opens Q1');

$sales130 = ['base_total_ytd'=>3445.0,'irpf_total_ytd'=>531.75];
$cost130 = ['base_total_ytd'=>281.58];
$input = $review + ['overrides'=>['05'=>72.5]];
$d = Tax::calculate('130',2026,3,$sales130,$cost130,$input);
equal($d['fields']['07']['value'],28.43,'Screenshot preliminary amount');
equal($d['result'],28.43,'Final amount with zero deductions');
check($d['ready'],'Complete 130 ready');
foreach ([9000.0=>100.0,10000.0=>75.0,11000.0=>50.0,12000.0=>25.0,12001.0=>0.0] as $annual=>$minor) {
    $i = array_replace($input,['previous_net'=>(float)$annual]);
    $d = Tax::calculate('130',2026,3,$sales130,$cost130,$i);
    equal($d['fields']['13']['value'],$minor,'Annual minor threshold');
}
$d = Tax::calculate('130',2026,3,$sales130,$cost130,array_replace($input,['previous_net'=>9000.0]));
equal($d['fields']['07']['value'],28.43,'Minor does not change box 07');
equal($d['result'],-71.57,'Final box 19 includes minor');
$d = Tax::calculate('130',2026,3,$sales130,$cost130);
check($d['result'] === null && !$d['ready'],'Missing history and prior-year net never guessed');
$d = Tax::calculate('130',2026,1,['base_total_ytd'=>100.0,'irpf_total_ytd'=>50.0],['base_total_ytd'=>200.0],array_replace($review,['previous_net'=>0.0]));
equal($d['fields']['03']['value'],-100.0,'Negative profit retained');
equal($d['fields']['04']['value'],0.0,'Negative profit tax zero');
equal($d['fields']['07']['value'],-50.0,'Negative preliminary amount retained');
equal($d['fields']['12']['value'],0.0,'Box 12 floor at zero');
equal($d['result'],-100.0,'Negative minor can carry to future quarters');
$h = [record('130',2026,1,['07'=>150.0,'16'=>10.0,'19'=>40.0,'15'=>0.0]),record('130',2026,2,['07'=>-50.0,'16'=>0.0,'19'=>-100.0,'15'=>0.0])];
$d = Tax::calculate('130',2026,3,['base_total_ytd'=>2000.0,'irpf_total_ytd'=>100.0],[],array_replace($review,['no_previous'=>false]),$h);
equal($d['fields']['05']['value'],140.0,'Previous payment uses positive 07 minus 16, not 19');
equal($d['fields']['15']['value'],100.0,'Previous negatives carried');
equal($d['result'],60.0,'Previous negatives applied');
$h[] = record('130',2026,3,['07'=>160.0,'16'=>0.0,'19'=>60.0,'15'=>100.0]);
equal(Tax::previous('130',2026,4,$h)['negative'],0.0,'Consumed losses do not get deducted twice');
$h[] = record('130',2026,2,['07'=>25.0,'16'=>0.0,'19'=>0.0,'15'=>0.0],'2026-10-06 11:00:00');
equal(Tax::previous('130',2026,3,$h)['payment'],165.0,'Latest correction replaces earlier quarter');
$unfiled = record('130',2026,1,['07'=>999.0]); $unfiled['status']='draft';
check(!Tax::previous('130',2026,2,[$unfiled])['complete'],'Drafts never count as filed');
$d = Tax::calculate('130',2026,1,['base_total_ytd'=>500.0],[],array_replace($review,['negative_balance'=>1000.0]));
equal($d['fields']['15']['value'],100.0,'Loss deduction capped at box 14');
$d = Tax::calculate('130',2026,1,['base_total_ytd'=>500.0],[],array_replace($review,['housing'=>true,'overrides'=>['16'=>300.0]]));
check(!$d['ready'],'Housing over allowed result blocked');
$d = Tax::calculate('130',2026,1,['base_total_ytd'=>500.0],[],array_replace($review,['agricultural'=>true]));
check($d['fields']['08']['value'] === null && $d['fields']['10']['value'] === null,'Agricultural input cannot be guessed');

$sales303 = ['base_total'=>1995.0,'iva_total'=>418.95,'by_vat'=>['21.00'=>['base'=>1995.0,'iva'=>418.95]]];
$d = Tax::calculate('303',2026,3,$sales303,[],$review);
equal($d['fields']['07']['value'],1995.0,'PDF box 07');
equal($d['fields']['08']['value'],21.0,'PDF box 08');
foreach (['09','27','46','64','66','69','71'] as $box) { equal($d['fields'][$box]['value'],418.95,'PDF box ' . $box); }
check($d['ready'],'PDF ordinary 303 ready');
$d = Tax::calculate('303',2026,3,$sales303,[],array_replace($review,['no_previous'=>false]));
check($d['result'] === null,'Unknown VAT carry never defaults to zero');
$d = Tax::calculate('303',2026,3,$sales303,[],array_replace($review,['no_previous'=>false,'overrides'=>['110'=>600.0]]));
equal($d['fields']['78']['value'],418.95,'VAT carry capped at tax due');
equal($d['fields']['87']['value'],181.05,'Unused VAT carry retained');
equal($d['result'],0.0,'VAT carry offsets tax due');
$expense303 = ['vat_total'=>500.0,'by_vat'=>['21.00'=>['base'=>2380.95,'vat'=>500.0]]];
$d = Tax::calculate('303',2026,3,$sales303,$expense303,array_replace($review,['overrides'=>['110'=>200.0]]));
equal($d['result'],-81.05,'Negative VAT result');
equal($d['fields']['78']['value'],0.0,'No previous balance consumed when negative');
equal($d['fields']['87']['value'],200.0,'Old VAT balance remains separate');
equal($d['fields']['72']['value'],81.05,'New negative amount becomes compensation');
$h = [record('303',2025,4,['87'=>200.0,'72'=>81.05])];
equal(Tax::previous('303',2026,1,$h)['vat_balance'],281.05,'VAT carry includes previous year and new compensation');
$h[] = record('303',2025,4,['87'=>200.0,'72'=>0.0],'2026-10-06 11:00:00');
equal(Tax::previous('303',2026,1,$h)['vat_balance'],200.0,'Refund is not carried as compensation');
$d = Tax::calculate('303',2026,4,$sales303,$expense303,array_replace($review,['refund_choice'=>'refund']));
equal($d['fields']['73']['value'],81.05,'Q4 refund amount');
equal($d['fields']['72']['value'],0.0,'Refund does not create new compensation');
$d = Tax::calculate('303',2026,3,$sales303,$expense303,array_replace($review,['refund_choice'=>'refund']));
check(!$d['ready'],'Unsupported Q3 refund blocked');
$d = Tax::calculate('303',2026,3,$sales303,[],array_replace($review,['overrides'=>['07'=>1000.0]]));
equal($d['fields']['09']['value'],210.0,'Correcting base also recalculates its VAT');
$d = Tax::calculate('303',2026,3,$sales303,[],array_replace($review,['overrides'=>['10'=>100.0,'11'=>21.0,'36'=>100.0,'37'=>21.0]]));
equal($d['result'],418.95,'Intracommunity acquisition output and deductible VAT cancel');
$d = Tax::calculate('303',2026,3,['by_vat'=>['0.00'=>['base'=>100.0,'iva'=>0.0]]],[],$review);
check(!$d['ready'],'Zero-rated and exempt sales require classification');
$d = Tax::calculate('303',2026,3,['by_vat'=>['0.00'=>['base'=>100.0,'iva'=>0.0]]],[],array_replace($review,['overrides'=>['150'=>0.0,'120'=>100.0]]));
check($d['ready'],'Explicit non-taxable classification resolves review');
$invalid = false;
try { Tax::input(['boxes'=>['05'=>'12junk']],'130'); } catch (InvalidArgumentException $e) { $invalid = true; }
check($invalid,'Invalid box cannot silently coerce');
$input = Tax::input(['boxes'=>['07'=>'1.234,56','71'=>'9'],'review_records'=>'1','previous_net'=>'0'],'303');
equal($input['overrides']['07'],1234.56,'Localized adjustment input');
check(!isset($input['overrides']['71']),'Calculated final result cannot be overridden');
$newer = record('130',2026,1,['07'=>50.0,'16'=>0.0,'19'=>50.0]); $newer['filed_date']='2026-05-01';
$older = record('130',2026,1,['07'=>999.0,'16'=>0.0,'19'=>999.0],'2026-10-06 20:00:00'); $older['filed_date']='2026-04-01';
equal(Tax::previous('130',2026,2,[$newer,$older])['payment'],50.0,'Importing older filing later cannot replace latest correction');
$d = Tax::calculate('303',2025,3,$sales303,[],$review);
check(!$d['ready'],'Unverified form years cannot be marked ready');
check(!Tax::catalog('130')['13']['aeat_calculated'],'Minor calculated by Moni must still be entered');
$d = Tax::calculate('303',2026,3,$sales303,[],array_replace($review,['overrides'=>['156'=>100.0]]));
equal($d['fields']['158']['value'],1.75,'Equivalence surcharge uses its fixed percentage');
$d = Tax::calculate('303',2026,3,['by_vat'=>['5.00'=>['base'=>100.0,'iva'=>5.0]]],[],array_replace($review,['additional_rates_reviewed'=>true,'overrides'=>['153'=>100.0,'154'=>5.0]]));
equal($d['fields']['155']['value'],5.0,'Additional VAT quota follows explicit base and rate');
check($d['ready'],'Explicit additional rate can resolve review');
$before = Tax::signature($d,$review);
$d['fields']['07']['value'] = 999.0;
check($before !== Tax::signature($d,$review),'Changing records invalidates review');
echo "OK: $checks fiscal checks passed.\n";
