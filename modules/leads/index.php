<?php
$pageTitle = 'Manage Leads';
require_once '../../config/auth.php';
requireLogin();
require_once '../../config/db.php';
require_once '../../models/Lead.php';
require_once '../../models/User.php';


$orgId = getOrgId();
$leadModel = new Lead($pdo);
$userModel = new User($pdo);

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $ids = $_POST['lead_ids'] ?? [];
    if (!empty($ids)) {
        switch ($_POST['bulk_action']) {
            case 'delete':
                $leadModel->bulkDelete($ids);
                redirect(BASE_URL . 'modules/leads/', count($ids) . ' leads deleted.', 'success');
                break;
            case 'assign':
                if (!empty($_POST['bulk_agent'])) {
                    $leadModel->bulkAssign($ids, $_POST['bulk_agent'], getUserId());
                    redirect(BASE_URL . 'modules/leads/', count($ids) . ' leads assigned.', 'success');
                }
                break;
            default:
                // Status change
                if ($_POST['bulk_action']) {
                    $leadModel->bulkUpdateStatus($ids, $_POST['bulk_action'], getUserId());
                    redirect(BASE_URL . 'modules/leads/', count($ids) . ' leads updated.', 'success');
                }
        }
    }
}

// Filters
$filters = [
    'search'      => $_GET['search'] ?? '',
    'status'      => $_GET['status'] ?? '',
    'priority'    => $_GET['priority'] ?? '',
    'source'      => $_GET['source'] ?? '',
    'assigned_to' => $_GET['assigned_to'] ?? '',
    'date_from'   => $_GET['date_from'] ?? '',
    'date_to'     => $_GET['date_to'] ?? '',
    'tag_id'      => $_GET['tag_id'] ?? '',
];

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$leads = $leadModel->getAllLeads($orgId, $filters, $limit, $offset);
$totalLeads = $leadModel->getTotalLeadsCount($orgId, $filters);
$totalPages = ceil($totalLeads / $limit);
$agents = $userModel->getAgents($orgId);
$tags = $leadModel->getOrgTags($orgId);
$sources = $leadModel->getSources($orgId);

include '../../includes/header.php';
?>

<!-- Filter Bar -->
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <input type="text" class="form-control form-control-sm" name="search" placeholder="Search..." value="<?= e($filters['search']) ?>">
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="status">
                    <option value="">All Status</option>
                    <?php foreach (['New Lead','Contacted','Working','Qualified','Processing','Proposal Sent','Follow Up','Negotiation','Not Picked','Done','Closed Won','Closed Lost','Rejected'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <select class="form-select form-select-sm" name="priority">
                    <option value="">Priority</option>
                    <option value="Hot" <?= $filters['priority']==='Hot'?'selected':'' ?>>🔥 Hot</option>
                    <option value="Warm" <?= $filters['priority']==='Warm'?'selected':'' ?>>☀️ Warm</option>
                    <option value="Cold" <?= $filters['priority']==='Cold'?'selected':'' ?>>❄️ Cold</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="assigned_to">
                    <option value="">All Agents</option>
                    <?php foreach ($agents as $agent): ?>
                        <option value="<?= $agent['id'] ?>" <?= $filters['assigned_to'] == $agent['id'] ? 'selected' : '' ?>><?= e($agent['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control form-control-sm" name="date_from" value="<?= e($filters['date_from']) ?>" placeholder="From">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control form-control-sm" name="date_to" value="<?= e($filters['date_to']) ?>" placeholder="To">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<!-- Leads Table -->
<div class="card shadow-sm border-0">
    <div class="card-header bg-white border-0 pt-4 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0"><i class="bi bi-people me-2 text-primary"></i>Leads <span class="badge bg-primary bg-opacity-10 text-primary ms-2"><?= $totalLeads ?></span></h6>
        <div>
            <a href="<?= BASE_URL ?>modules/leads/add.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Lead</a>
        </div>
    </div>
    <div class="card-body pt-0">
        <form method="POST" id="bulkForm">
            <!-- Bulk Actions Bar -->
            <div class="d-flex align-items-center gap-2 mb-3 p-2 rounded bg-light" id="bulkBar" style="display:none !important;">
                <span class="small fw-semibold text-muted" id="selectedCount">0 selected</span>
                <select name="bulk_action" class="form-select form-select-sm" style="width:160px;">
                    <option value="">Bulk Action</option>
                    <option value="delete">Delete Selected</option>
                    <optgroup label="Change Status">
                        <option value="New Lead">New Lead</option>
                        <option value="Working">Working</option>
                        <option value="Follow Up">Follow Up</option>
                        <option value="Done">Done</option>
                        <option value="Rejected">Rejected</option>
                    </optgroup>
                </select>
                <select name="bulk_agent" class="form-select form-select-sm" style="width:160px;">
                    <option value="">Assign To...</option>
                    <?php foreach ($agents as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= e($a['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-dark btn-sm" onclick="return confirm('Are you sure?')"><i class="bi bi-check2-all me-1"></i>Apply</button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th width="30"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Source</th>
                            <th>Agent</th>
                            <th>Date</th>
                            <th width="80">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leads as $lead): ?>
                        <tr>
                            <td><input type="checkbox" name="lead_ids[]" value="<?= $lead['id'] ?>" class="form-check-input lead-check"></td>
                            <td><a href="<?= BASE_URL ?>modules/leads/view.php?id=<?= $lead['id'] ?>" class="fw-semibold text-dark text-decoration-none"><?= e($lead['name']) ?></a><br><small class="text-muted"><?= e($lead['company'] ?: '') ?></small></td>
                            <td class="small"><?= e($lead['phone']) ?></td>
                            <td><span class="badge <?= getStatusBadgeClass($lead['status']) ?> rounded-pill px-2 py-1"><?= e($lead['status']) ?></span></td>
                            <td><span class="badge <?= getPriorityBadgeClass($lead['priority'] ?? 'Warm') ?> rounded-pill px-2 py-1"><i class="bi <?= getPriorityIcon($lead['priority'] ?? 'Warm') ?> me-1"></i><?= e($lead['priority'] ?? 'Warm') ?></span></td>
                            <td class="small text-muted"><?= e($lead['source'] ?: '—') ?></td>
                            <td class="small"><?= e($lead['agent_name'] ?: 'Unassigned') ?></td>
                            <td class="small text-muted"><?= formatDate($lead['created_at'], 'M d') ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= BASE_URL ?>modules/leads/view.php?id=<?= $lead['id'] ?>" class="btn btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                    <a href="<?= BASE_URL ?>modules/leads/edit.php?id=<?= $lead['id'] ?>" class="btn btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($leads)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No leads found. <a href="<?= BASE_URL ?>modules/leads/add.php">Add a lead</a></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav class="mt-3">
            <ul class="pagination pagination-sm justify-content-center mb-0">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.lead-check').forEach(cb => cb.checked = this.checked);
    updateBulkBar();
});
document.querySelectorAll('.lead-check').forEach(cb => cb.addEventListener('change', updateBulkBar));

function updateBulkBar() {
    const checked = document.querySelectorAll('.lead-check:checked').length;
    const bar = document.getElementById('bulkBar');
    bar.style.display = checked > 0 ? 'flex' : 'none';
    bar.style.setProperty('display', checked > 0 ? 'flex' : 'none', 'important');
    document.getElementById('selectedCount').textContent = checked + ' selected';
}
</script>

<?php include '../../includes/footer.php'; ?>


