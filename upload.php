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
    
    $correctData = [];
    $incompleteData = [];
    $errorData = [];
    $ignoredData = [];

    foreach ($csvData as $row) {
        // Clean up data by removing empty columns
        $cleanedRow = array_values(array_filter($row, fn($value) => trim($value) !== ''));
        
        // Handle rows with "; ; ; ;"
        if (count($row) > 3 && count($cleanedRow) === 0) {
            $ignoredData[] = implode(';', $row);
        } elseif (count($cleanedRow) === 3) { // Valid row
            $correctData[] = $cleanedRow;
        } elseif (count($cleanedRow) >= 2) { // Incomplete row
            $incompleteData[] = $cleanedRow;
        } else { // Erroneous row
            $errorData[] = $row;
        }
    }

    // PDF class extension
    class PDF extends FPDF {
        // Header
        function Header() {
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 10, 'Product List', 0, 1, 'C');
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
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 10, $title, 0, 1, 'L');
            $this->Ln(5);

            $this->SetFillColor(255, 0, 0); // Header background color
            $this->SetTextColor(255); // Header text color
            $this->SetDrawColor(128, 0, 0); // Border color
            $this->SetLineWidth(.3);
            $this->SetFont('', 'B');

            // Header
            $w = array(30, 100, 40); // Column widths
            foreach ($header as $i => $col) {
                $this->Cell($w[$i], 7, $col, 1, 0, 'C', true);
            }
            $this->Ln();

            // Reset colors and fonts
            $this->SetFillColor(224, 235, 255);
            $this->SetTextColor(0);
            $this->SetFont('');

            // Data
            $fill = false;
            foreach ($data as $row) {
                // Ensure each column exists before printing
                $this->Cell($w[0], 6, isset($row[0]) ? $row[0] : '', 'LR', 0, 'L', $fill);
                $this->Cell($w[1], 6, isset($row[1]) ? $row[1] : '', 'LR', 0, 'L', $fill);
                $this->Cell($w[2], 6, isset($row[2]) ? $row[2] : '', 'LR', 0, 'R', $fill);
                $this->Ln();
                $fill = !$fill;
            }
            $this->Cell(array_sum($w), 0, '', 'T');
            $this->Ln(10);
        }

        function FancyTableSingleColumn($data, $title) {
            $this->SetFont('Arial', 'B', 12);
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
    if (count($correctData) > 0) {
        $pdf->FancyTable(['ID', 'Product Description', 'Price'], $correctData, 'Correct Data');
    }
    
    // Incomplete Data
    if (count($incompleteData) > 0) {
        $pdf->FancyTable(['ID', 'Product Description', 'Price'], $incompleteData, 'Incomplete Data');
    }
    
    // Error Data
    if (count($errorData) > 0) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'Error Data', 0, 1, 'L');
        $pdf->Ln(5);
        $pdf->SetFont('Arial', '', 12);
        foreach ($errorData as $row) {
            $pdf->Cell(0, 10, implode(';', $row), 0, 1);
        }
        $pdf->Ln(10);
    }
    
    // Ignored Data
    if (count($ignoredData) > 0) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'Ignored Data', 0, 1, 'L');
        $pdf->Ln(5);
        $pdf->SetFont('Arial', '', 12);
        foreach ($ignoredData as $row) {
            $pdf->Cell(0, 10, $row, 0, 1);
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
