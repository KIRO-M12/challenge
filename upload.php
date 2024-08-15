<?php
require('fpdf/fpdf.php');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    $fileType = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
    if ($fileType !== 'csv') {
        die("Please upload a valid CSV file.");
    }

    $file = $_FILES['file']['tmp_name'];

    // Check if file exists and is readable
    if (!is_readable($file)) {
        die("The uploaded file is not readable.");
    }

    // Read and clean CSV data
    $csvData = array_map('str_getcsv', file($file, FILE_SKIP_EMPTY_LINES), array_fill(0, count(file($file)), ';'));
    if ($csvData === false) {
        die("Error reading the CSV file.");
    }

    $cleanedData = [];
    $incompleteData = [];
    $specialCharData = [];
    $errorsFound = false;

    // Skip the first row (headers)
    $headers = array_shift($csvData);

    foreach ($csvData as $row) {
        // Trim each element in the row and remove empty values
        $cleanedRow = array_map('trim', array_slice($row, 0, 3));

        // Check if the row is complete
        if (count($cleanedRow) < 3 || in_array('', $cleanedRow, true)) {
            $incompleteData[] = $row; // Store the original row if incomplete
            $errorsFound = true;
        } else {
            $cleanedData[] = $cleanedRow;
        }

        // Check for special characters in the original row
        $originalRow = implode(';', $row);
        if (preg_match('/[^\x20-\x7E\xA0-\xFF]/u', $originalRow)) {
            $specialCharData[] = $originalRow;
            $errorsFound = true;
        }
    }

    // PDF class extension
    class PDF extends FPDF {
        function Header() {
            $this->SetFont('Arial', 'B', 20);
            $this->Cell(0, 15, 'Product List Report', 0, 1, 'C');
            $this->SetFont('Arial', 'I', 12);
            $this->Cell(0, 10, 'Generated on ' . date('Y-m-d'), 0, 1, 'C');
            $this->Ln(5);
        }

        function Footer() {
            $this->SetY(-20);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->PageNo(), 0, 0, 'C');
            $this->Ln(5);
            $this->Cell(0, 10, 'Confidential Document', 0, 0, 'C');
        }

        function FancyTable($header, $data, $title, $errorsFound) {
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, $title, 0, 1, 'L');
            if ($errorsFound) {
                $this->SetFont('Arial', 'B', 12);
                $this->SetTextColor(255, 0, 0);
                $this->Cell(0, 10, '(Errors found in this table. Details are mentioned below.)', 0, 1, 'L');
                $this->SetTextColor(0, 0, 0);
            }
            $this->Ln(5);

            // Set colors, line width, and font for header
            $this->SetFillColor(0, 102, 204);
            $this->SetTextColor(255);
            $this->SetDrawColor(0, 51, 102);
            $this->SetLineWidth(.3);
            $this->SetFont('', 'B');

            $w = array(30, 100, 40);
            foreach ($header as $i => $col) {
                $this->Cell($w[$i], 12, $col, 1, 0, 'C', true);
            }
            $this->Ln();

            $this->SetFillColor(224, 235, 255);
            $this->SetTextColor(0);
            $this->SetFont('');

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

        function ListErrors($data, $title) {
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

    $pdf = new PDF();
    $pdf->AddPage();

    if (count($cleanedData) > 0) {
        $pdf->FancyTable(['ID', 'Produktnavn', 'Pris'], $cleanedData, 'Cleaned Data', $errorsFound);
    }

    if (count($incompleteData) > 0) {
        $pdf->FancyTable(['ID', 'Produktnavn', 'Pris'], $incompleteData, 'Incomplete Data', true);
    }

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

    if ($errorsFound) {
        $pdf->ListErrors(array_merge($incompleteData, $specialCharData), 'Details of Errors Found:');
    }

    ob_clean();
    $pdf->Output('I', 'products.pdf');
} else {
    echo "No file uploaded or invalid file.";
}
?>
