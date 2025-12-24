<?php
require_once '../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

include '../auth/db.php';

if (!isset($_GET['id'])) {
    die("Report ID not specified.");
}

$report_id = $_GET['id'];

$stmt = $conn->prepare("SELECT * FROM event_reports WHERE id = ?");
$stmt->bind_param("i", $report_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Report not found.");
}

$report = $result->fetch_assoc();

// Format date and time
$dateTime = strtotime($report['date_time']);
$eventDate = date("F j, Y", $dateTime);
$eventTime = date("g:i A", $dateTime);

// Setup Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isPhpEnabled', true);

$dompdf = new Dompdf($options);

// Image handling
$image_paths = json_decode($report['image_paths'], true);
$imgHTML = '';

if (!empty($image_paths) && is_array($image_paths)) {
    $imgHTML = '<table width="100%" cellspacing="0" cellpadding="5" style="margin-top: 10px;">';
    $i = 0;
    foreach ($image_paths as $img) {
        $absolutePath = realpath('../uploads/reports/' . $img);
        if ($absolutePath && file_exists($absolutePath)) {
            $imageData = file_get_contents($absolutePath);
            $imgType = pathinfo($absolutePath, PATHINFO_EXTENSION);
            $base64 = base64_encode($imageData);

            if ($i % 2 == 0) {
                $imgHTML .= '<tr>';
            }

            $imgHTML .= '
                <td width="50%" style="padding: 5px;">
                    <div style="border: 1px solid #ccc; padding: 4px;">
                        <img src="data:image/'.$imgType.';base64,'.$base64.'" style="width: 100%; height: auto; max-height: 200px; object-fit: contain;">
                    </div>
                </td>
            ';

            if ($i % 2 == 1) {
                $imgHTML .= '</tr>';
            }

            $i++;
        }
    }

    // If odd number of images, close the last row
    if ($i % 2 != 0) {
        $imgHTML .= '<td width="50%"></td></tr>';
    }

    $imgHTML .= '</table>';
}

// Function to process text for better formatting
function processText($text) {
    // Convert line breaks
    $text = nl2br(htmlspecialchars($text));
    
    // Handle bullet points and lists
    $text = preg_replace('/•\s*/', '• ', $text);
    $text = preg_replace('/\*\s*/', '• ', $text);
    $text = preg_replace('/-\s*/', '• ', $text);
    
    // Ensure proper spacing for lists
    $text = preg_replace('/(•\s)/', '<span style="margin-right: 5px;">•</span> ', $text);
    
    return $text;
}

// HTML & CSS with improved styling
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Event Report | '.htmlspecialchars($report['event_name']).'</title>
    <style>
        @page {
            margin: 70px 50px 80px 50px;
        }
        
        body {
            font-family: "DejaVu Sans", "Helvetica", Arial, sans-serif;
            font-size: 13px;
            line-height: 1.6;
            color: #2c3e50;
            padding: 0;
            margin: 0;
            background: #ffffff;
        }
        
        .header {
            position: fixed;
            top: -60px;
            left: 0;
            right: 0;
            height: 50px;
            border-bottom: 2px solid #5DADE2;
            text-align: center;
            background: linear-gradient(135deg, #5DADE2 0%, #A569BD 100%);
            color: white;
        }
        
        .header h1 {
            font-size: 20px;
            font-weight: 600;
            margin: 12px 0 0;
            color: white;
            letter-spacing: 1px;
        }
        
        .footer {
            position: fixed;
            bottom: -60px;
            left: 0;
            right: 0;
            height: 50px;
            border-top: 1px solid #D5D8DC;
            text-align: center;
            font-size: 10px;
            color: #7f8c8d;
            padding-top: 15px;
            background: #FBFCFC;
        }
        
        .page-info {
            font-size: 10px;
            color: #5DADE2;
            font-weight: 600;
        }
        
        .watermark {
            position: fixed;
            top: 40%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 80px;
            color: rgba(93, 173, 226, 0.05);
            z-index: -1000;
            font-weight: 600;
            white-space: nowrap;
            font-family: "DejaVu Sans", sans-serif;
        }
        
        .content {
            padding-top: 20px;
        }
        
        .report-title {
            font-size: 22px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 3px solid #5DADE2;
            text-align: center;
            background: linear-gradient(135deg, #2c3e50 0%, #5DADE2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #5DADE2;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 2px solid #A569BD;
            background: #FBFCFC;
            padding: 8px 12px;
            border-radius: 6px;
            border-left: 4px solid #5DADE2;
        }
        
        .section-content {
            padding: 0 15px;
            font-size: 13px;
            line-height: 1.7;
        }
        
        .section-content ul, .section-content ol {
            margin: 8px 0;
            padding-left: 25px;
        }
        
        .section-content li {
            margin-bottom: 6px;
            line-height: 1.6;
        }
        
        .info-grid {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 20px;
            background: #FBFCFC;
            border: 1px solid #D5D8DC;
            border-radius: 8px;
            padding: 15px;
        }
        
        .info-item {
            width: 33.33%;
            margin-bottom: 12px;
            padding: 0 10px;
        }
        
        .info-label {
            font-weight: 700;
            color: #5DADE2;
            display: block;
            font-size: 12px;
            margin-bottom: 2px;
        }
        
        .info-value {
            font-weight: 500;
            color: #2c3e50;
            font-size: 13px;
        }
        
        .bullet-point {
            display: inline-block;
            width: 20px;
            text-align: center;
            color: #A569BD;
            font-weight: bold;
        }
        
        .text-content {
            text-align: justify;
        }
        
        .page-break {
            page-break-before: always;
        }
        
        /* Custom bullet points */
        .custom-bullet {
            color: #A569BD;
            font-weight: bold;
            margin-right: 8px;
        }
        
        .highlight-box {
            background: linear-gradient(135deg, #FBFCFC 0%, #f8fafc 100%);
            border: 1px solid #D5D8DC;
            border-left: 4px solid #5DADE2;
            border-radius: 6px;
            padding: 12px 15px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>EVENT REPORT - IMPERIUM TRUST</h1>
    </div>
    
    <div class="footer">
        <span class="page-info">IMPERIUM TRUST</span> | 
        <span class="page-number"></span> | 
        Generated on: '.date('F j, Y').'
    </div>
    
    <div class="watermark">IMPERIUM TRUST</div>
    
    <div class="content">
        <div class="report-title">'.htmlspecialchars($report['event_name']).'</div>
        
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">EVENT TYPE</span>
                <span class="info-value">'.htmlspecialchars($report['type_of_event']).'</span>
            </div>
            <div class="info-item">
                <span class="info-label">DATE</span>
                <span class="info-value">'.$eventDate.'</span>
            </div>
            <div class="info-item">
                <span class="info-label">TIME</span>
                <span class="info-value">'.$eventTime.'</span>
            </div>
            <div class="info-item">
                <span class="info-label">LOCATION</span>
                <span class="info-value">'.htmlspecialchars($report['location']).'</span>
            </div>
            <div class="info-item">
                <span class="info-label">ORGANIZED BY</span>
                <span class="info-value">'.htmlspecialchars($report['organised_by']).'</span>
            </div>
            <div class="info-item">
                <span class="info-label">TARGET AUDIENCE</span>
                <span class="info-value">'.htmlspecialchars($report['target_audience']).'</span>
            </div>
        </div>';
        
        // Define sections with proper processing
        $sections = [
            'OBJECTIVES' => $report['objective'],
            'KEY HIGHLIGHTS' => $report['key_highlights'],
            'EVENT SCHEDULE' => $report['event_schedule'],
            'OUTCOMES AND IMPACT' => $report['outcomes_and_impact'],
            'PARTNERS AND SPONSORS' => $report['partners_and_sponsors'],
            'CHALLENGES AND LEARNING' => $report['challenges_and_learning'],
            'BUDGET' => $report['budget'],
            'ANNEXURE' => $report['annexure'],
            'CONCLUSION' => $report['conclusion']
        ];
        
        $sectionNumber = 1;
        foreach ($sections as $title => $content) {
            if (!empty(trim($content))) {
                $html .= '
                <div class="section">
                    <div class="section-title">'.$sectionNumber.'. '.$title.'</div>
                    <div class="section-content text-content">'.processText($content).'</div>
                </div>';
                $sectionNumber++;
            }
        }
        
        // Images section
        if (!empty($image_paths)) {
            $html .= '
            <div class="section">
                <div class="section-title">'.$sectionNumber.'. EVENT IMAGES</div>
                <div class="section-content">'.$imgHTML.'</div>
            </div>';
        }

$html .= '
    </div>
</body>
</html>';

// Load HTML
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');

// Render PDF
$dompdf->render();

// Get the total number of pages
$canvas = $dompdf->getCanvas();
$total_pages = $canvas->get_page_count();

// Add proper page numbering using page_script
$dompdf->getCanvas()->page_script('
    $font = $fontMetrics->getFont("DejaVu Sans", "normal");
    $current_page = $PAGE_NUM;
    $total_pages = $PAGE_COUNT;

    // Footer page info
    $text = "Page " . $current_page . " of " . $total_pages;
    $pdf->text(500, 810, $text, $font, 10, [0.2, 0.2, 0.2]);

    // Add header line and text on pages after the first
    if ($current_page > 1) {
        // Draw a thin line (width is the 6th argument)
        $pdf->line(50, 60, 545, 60, [0, 0, 0], 0.5);
        $pdf->text(50, 45, "Event Report: ' . addslashes(htmlspecialchars($report['event_name'])) . '", $font, 9, [0.4, 0.4, 0.4]);
    }
');


// Output
$filename = "event_report_" . preg_replace('/[^a-zA-Z0-9]/', '_', strtolower($report['event_name'])) . ".pdf";
$dompdf->stream($filename, ["Attachment" => false]);
exit;
?>