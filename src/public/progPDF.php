<?
// include 'TCPDF-master/tcpdf/config/lang/eng.php';
// include 'TCPDF-master/tcpdf.php';

header('Pragma: no-cache');
header('Cache-control: no-cache');

// create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Aidan Nichol');
$pdf->SetTitle("St. Edward's Fellwalkers $Year Walk Pogramme");
$pdf->SetSubject('TCPDF Tutorial');
$pdf->SetKeywords('St. Edwards, Programme, Walking');

$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

//set margins
$pdf->SetMargins(10, 10, 10);
$pdf->SetHeaderMargin(0);

//set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 10);

//set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

//set some language-dependent strings
// $pdf->setLanguageArray($l);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// ---------------------------------------------------------

// set font
$pdf->SetFont('times', '', 12);

// add a page
$pdf->AddPage();
//$pdf->MultiCell(0,0,$_SERVER['DOCUMENT_ROOT']);
// $pdf->image(Kohana::find_file("media", "images/Phoenix logo B&W", true,  "png"),15,7, 0,30);
$pdf->SetFont('times', 'B', 24);

$pdf->SetFont('times', '', 16);
$pdf->MultiCell(0,0,strtoupper("Walks Programme - $Year"), 0,'C', 0,0,40,27, true);
$pdf->SetFont('times', 'B', 12);
$pdf->ln();
// Column Headins
$pdf->MultiCell(40, 5.5, "Date", 1, 'C', 0, 0, '', '', true);
$pdf->MultiCell(12, 5.5, "Time", 1, 'C', 0, 0,'', '', true);
$pdf->MultiCell(50, 5.5, "location", 1, 'C', 0, 0, '', '', true);
$pdf->MultiCell(15, 5.5, "Area", 1, 'C', 0, 0, '', '', true);
$pdf->MultiCell(40, 5.5, "Organizer", 1, 'C', 0, 0, '', '', true);

$pdf->ln();
$pdf->SetFont('helvetica', '', 10);
// Kint::dump($walksProg);
// set color for filler
$pdf->SetFillColor(102, 204, 255);
ksort($walksProg);
foreach($walksProg['walksDetails'] as $walkId=>$wk):

    $pdf->MultiCell(40, 5.5, date("jS F", strtotime($wk["date"])), 1, 'C', 0, 0, '', '', true, 0, false, true, 5.5, 'M');
    $pdf->MultiCell(12, 5.5, $wk["time"], 1, 'C', 0, 0, '', '', true, 0, false, true, 5.5, 'M');

    $pdf->MultiCell(50, 5.5, $wk["area"], 1, 'C', 0, 0, '', '', true, 0, false, true, 5.5, 'M');
    $pdf->MultiCell(15, 5.5, $wk["region"], 1, 'C', 0, 0, '', '', true, 0, false, true, 5.5, 'M');

    $pdf->MultiCell(40, 5.5, $wk["organizer"], 1, 'C', 0, 0, '', '', true, 0, false, true, 5.5, 'M');
    $pdf->ln();
endforeach;

$pdf->setY($pdf->getY()+10);


// $x1 = $pdf->getX();  $y1 = $pdf->getY();
// $d=5;$c=6;
// $pdf->MultiCell(55, 0, "DEPARTURE TIMES", 1, 'C', 0, 1);
// $pdf->MultiCell(40, 0, "Monkseaton", 0, 'L', 0, 0, '', $y1+$c);
// $pdf->MultiCell(15, 0, "8:30", 0, 'L', 0, 1, $x1+40, $y1+$c);
// $pdf->MultiCell(40, 0, "North Shields Pool", 0, 'L', 0, 0, '', $y1+$c+1*$d);
// $pdf->MultiCell(15, 0, "8:40", 0, 'L', 0, 1, $x1+40, $y1+$c+1*$d);
// $pdf->MultiCell(40, 0, "Wallsend", 0, 'L', 0, 0, '', $y1+$c+2*$d);
// $pdf->MultiCell(15, 0, "8:10", 0, 'L', 0, 1, $x1+40, $y1+$c+2*$d);
// $pdf->MultiCell(40, 0, "Four Lane Ends", 0, 'L', 0, 0, '', $y1+$c+3*$d);
// $pdf->MultiCell(15, 0, "8:20", 0, 'L', 0, 1, $x1+40, $y1+$c+3*$d);
//
// $x2 = $pdf->getX();  $y2 = $pdf->getY();
// $pdf->MultiCell(55, $y2-$y1, "", 1, 'C', 0, 1, $x1, $y1);
//
// $pdf->MultiCell(80, 0, $Message, 1, 'L', 0, 1, $x1+ 80, $y1);
// $y3 = $pdf->getY();
// $pdf->setY(max($y3,$y2)+8);
//
// $pdf->MultiCell(0, 11, "Booking for this programme will commence ".
//         date("D j<\s\u\p>S</\s\u\p> M", strtotime( $StartBookings)).
//         " cost &pound;{$CostMemb}", 1, 'C', 0, 1, '', '', true, 0, true);
// $pdf->MultiCell(0, 0, "www.phoenixwalkingclub.org.uk", 0, 'C', 0, 1);
// $pdf->lastPage();

// ---------------------------------------------------------

//Close and output PDF document
$pdf->Output("/www/sites/steds-server/newStEdwardsWalksProgramme{$Year}.pdf", 'F');
