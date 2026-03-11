<?php
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/db.php';

// Handle Export Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'export') {
    $format = $_POST['format'] ?? 'csv';
    $export_type = $_POST['export_type'] ?? 'all';
    $status = $_POST['status'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';

    $sql = "SELECT id, name, phone, email, company, source, status, note, created_at FROM leads WHERE 1=1";
    $params = [];

    if ($export_type === 'filtered') {
        if (!empty($status)) {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }
    } elseif ($export_type === 'date_range') {
        if (!empty($start_date)) {
            $sql .= " AND DATE(created_at) >= :start_date";
            $params[':start_date'] = $start_date;
        }
        if (!empty($end_date)) {
            $sql .= " AND DATE(created_at) <= :end_date";
            $params[':end_date'] = $end_date;
        }
    }

    $sql .= " ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=leads_export_' . date('Y-m-d_His') . '.csv');
        $output = fopen('php://output', 'w');
        
        // Write headers
        fputcsv($output, ['ID', 'Name', 'Phone', 'Email', 'Company', 'Source', 'Status', 'Note', 'Date Added']);
        
        // Write rows
        foreach ($leads as $lead) {
            fputcsv($output, $lead);
        }
        fclose($output);
        exit;
    } else if ($format === 'excel') {
        // Since PhpSpreadsheet might not be available yet due to extension requirements,
        // For Excel, we can stream a basic HTML table format that Excel reads natively, or fallback to CSV
        if (file_exists('vendor/autoload.php')) {
            require 'vendor/autoload.php';
            try {
                $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                
                // Set Headers
                $headers = ['ID', 'Name', 'Phone', 'Email', 'Company', 'Source', 'Status', 'Note', 'Date Added'];
                $col = 'A';
                foreach ($headers as $header) {
                    $sheet->setCellValue($col . '1', $header);
                    $col++;
                }

                // Set Data
                $row = 2;
                foreach ($leads as $lead) {
                    $col = 'A';
                    foreach ($lead as $value) {
                        $sheet->setCellValue($col . $row, $value);
                        $col++;
                    }
                    $row++;
                }

                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment;filename="leads_export_' . date('Y-m-d_His') . '.xlsx"');
                header('Cache-Control: max-age=0');
                
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $writer->save('php://output');
                exit;
            } catch (Exception $e) {
                // Ignore and fallback to CSV if error
            }
        }
        
        // Fallback to CSV if PHPSpreadsheet isn't loaded
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=leads_export_' . date('Y-m-d_His') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Name', 'Phone', 'Email', 'Company', 'Source', 'Status', 'Note', 'Date Added']);
        foreach ($leads as $lead) {
            fputcsv($output, $lead);
        }
        fclose($output);
        exit;
    }
}

include '../../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white pb-0 border-0 pt-4">
                <h4 class="mb-0 fw-bold"><i class="bi bi-cloud-download text-success me-2"></i>Export Leads</h4>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="export_leads.php">
                    <input type="hidden" name="action" value="export">
                    
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Export Format</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="format" id="formatCSV" value="csv" checked>
                                <label class="form-check-label" for="formatCSV">
                                    <i class="bi bi-filetype-csv me-1"></i> CSV Document
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="format" id="formatExcel" value="excel">
                                <label class="form-check-label" for="formatExcel">
                                    <i class="bi bi-filetype-xlsx me-1"></i> Excel (.xlsx)
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Which leads to export?</label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="export_type" id="exportAll" value="all" checked onchange="toggleExportOptions()">
                            <label class="form-check-label" for="exportAll">All Leads</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="export_type" id="exportFiltered" value="filtered" onchange="toggleExportOptions()">
                            <label class="form-check-label" for="exportFiltered">Filtered by Status</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="export_type" id="exportDateRange" value="date_range" onchange="toggleExportOptions()">
                            <label class="form-check-label" for="exportDateRange">By Date Range</label>
                        </div>
                    </div>

                    <div id="statusFilterDiv" class="mb-4" style="display: none;">
                        <label class="form-label fw-semibold">Select Status</label>
                        <select class="form-select" name="status">
                            <option value="">Any Status</option>
                            <option value="New Lead">New Lead</option>
                            <option value="Working">Working</option>
                            <option value="Processing">Processing</option>
                            <option value="Follow Up">Follow Up</option>
                            <option value="Not Picked">Not Picked</option>
                            <option value="Done">Done</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>

                    <div id="dateRangeDiv" class="mb-4 row" style="display: none;">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Start Date</label>
                            <input type="date" class="form-control" name="start_date">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">End Date</label>
                            <input type="date" class="form-control" name="end_date">
                        </div>
                    </div>

                    <hr>
                    <button type="submit" class="btn btn-success px-4"><i class="bi bi-download me-2"></i>Download Export</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleExportOptions() {
    const exportType = document.querySelector('input[name="export_type"]:checked').value;
    document.getElementById('statusFilterDiv').style.display = exportType === 'filtered' ? 'block' : 'none';
    document.getElementById('dateRangeDiv').style.display = exportType === 'date_range' ? 'flex' : 'none';
}
</script>

<?php include '../../includes/footer.php'; ?>


