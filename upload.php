<?php
require('fpdf/fpdf.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    $fileType = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
    if ($fileType !== 'csv') {
        die("Please upload a valid CSV file.");
    }

    $file = $_FILES['file']['tmp_name'];

    // Read and clean CSV data
    $csvData = array_map('str_getcsv', file($file, FILE_SKIP_EMPTY_LINES));

    $cleanedData = [];
    $incompleteData = [];
    $errorData = [];
    $specialCharData = [];
    $ignoredData = [];
    
    foreach ($csvData as $row) {
        // Clean up data by removing empty columns and special characters
        $cleanedRow = array_values(array_filter($row, fn($value) => trim($value) !== ''));
        $cleanedRow = array_map(function($value) {
            return preg_replace('/[^a-zA-Z0-9\s.,-]/u', '', $value); // Remove special characters, preserve UTF-8
        }, $cleanedRow);

        if (count($cleanedRow) === 3) { // Valid row
            $cleanedData[] = $cleanedRow;
        } elseif (count($cleanedRow) > 0) { // Incomplete row
            $incompleteData[] = $cleanedRow;
        } else { // Erroneous row
            $errorData[] = $row;
        }
        
        // Check for special characters in the original row
        $originalRow = implode(';', $row);
        if (preg_match('/[^\x20-\x7E]/u', $originalRow)) {
            $specialCharData[] = $originalRow;
        }
    }

    // PDF class extension
    class PDF extends FPDF {
        // Header
        function Header() {
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, 'Product List Report', 0, 1, 'C');
            $this->Ln(10);
        }

        // Footer
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
        }

        // Table
        function FancyTable($header, $data, $title) {
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 10, $title, 0, 1, 'L');
            $this->Ln(5);

            // Set colors, line width, and font for header
            $this->SetFillColor(255, 0, 0); // Header background color
            $this->SetTextColor(255); // Header text color
            $this->SetDrawColor(128, 0, 0); // Border color
            $this->SetLineWidth(.3);
            $this->SetFont('', 'B');

            // Column widths
            $w = array(30, 100, 40); 
            foreach ($header as $i => $col) {
                $this->Cell($w[$i], 10, $col, 1, 0, 'C', true);
            }
            $this->Ln();

            // Reset colors and fonts
            $this->SetFillColor(224, 235, 255);
            $this->SetTextColor(0);
            $this->SetFont('');

            // Data
            $fill = false;
            foreach ($data as $row) {
                $this->Cell($w[0], 10, isset($row[0]) ? $row[0] : '', 'LR', 0, 'L', $fill);
                $this->Cell($w[1], 10, isset($row[1]) ? $row[1] : '', 'LR', 0, 'L', $fill);
                $this->Cell($w[2], 10, isset($row[2]) ? $row[2] : '', 'LR', 0, 'R', $fill);
                $this->Ln();
                $fill = !$fill;
            }
            $this->Cell(array_sum($w), 0, '', 'T');
            $this->Ln(10);
        }

        function FancyTableSingleColumn($data, $title) {
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 10, $title, 0, 1, 'L');
            $this->Ln(5);

            $this->SetFont('Arial', '', 12);
            foreach ($data as $row) {
                $this->MultiCell(0, 10, $row);
                $this->Ln();
            }
            $this->Ln(10);
        }
    }

    // Generate PDF
    $pdf = new PDF();
    $pdf->AddPage();

    // Correct Data
    if (count($cleanedData) > 0) {
        $pdf->FancyTable(['id', 'title', 'price'], $cleanedData, 'Cleaned Data');
    }

    // Incomplete Data
    if (count($incompleteData) > 0) {
        $pdf->FancyTable(['id', 'title', 'price'], $incompleteData, 'Incomplete Data');
    }

    // Error Data
    if (count($errorData) > 0) {
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, 'Error Data', 0, 1, 'L');
        $pdf->Ln(5);
        $pdf->SetFont('Arial', '', 12);
        foreach ($errorData as $row) {
            $pdf->MultiCell(0, 10, implode(';', $row));
            $pdf->Ln();
        }
        $pdf->Ln(10);
    }

    // Special Characters Data
    if (count($specialCharData) > 0) {
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, 'Special Characters Found', 0, 1, 'L');
        $pdf->Ln(5);
        $pdf->SetFont('Arial', '', 12);
        foreach ($specialCharData as $row) {
            $pdf->MultiCell(0, 10, $row);
            $pdf->Ln();
        }
        $pdf->Ln(10);
    }

    // Ignored Data
    if (count($ignoredData) > 0) {
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, 'Ignored Data', 0, 1, 'L');
        $pdf->Ln(5);
        $pdf->SetFont('Arial', '', 12);
        foreach ($ignoredData as $row) {
            $pdf->MultiCell(0, 10, $row);
            $pdf->Ln();
        }
        $pdf->Ln(10);
    }

    // Clean output buffer to prevent "Some data has already been output" error
    ob_clean();
    
    $pdf->Output('D', 'products.pdf');
} else {
    echo "No file uploaded or invalid file.";
}
?>
