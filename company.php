<?php
include 'config.php';
require 'inc/research.php';

require_once 'inc/scoring_engine.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
if (!isset($_GET['id'])) {
    die("No company specified.");
}
$companyId = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([$companyId]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$company) {
    die("Company not found.");
}

$scoring = getScoringData($pdo);
$companyMaterials = $scoring['company_mat_data'][$companyId] ?? [];
$totalScore = $scoring['company_raw_scores'][$companyId] ?? 0;


$forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] == 1;
$aiData = getCompanyResearch($pdo, $company, $forceRefresh);
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($company['company_name']) ?> – Sourcing Agent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .loading-overlay { display: none; position: fixed; top: 0; left: var(--sidebar-width); right: 0; bottom: 0; background: rgba(255,255,255,0.85); z-index: 1000; justify-content: center; align-items: center; flex-direction: column; }
        .sourcing-badge { font-size: 0.85rem; padding: 0.4rem 0.8rem; background-color: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); }
        .console-header { background-color: var(--primary-color); color: white; padding: 2rem; border-radius: 12px; margin-bottom: 24px; position: relative; overflow: hidden; }
        .console-header::after { content: ''; position: absolute; right: -20px; bottom: -50px; width: 150px; height: 150px; border-radius: 50%; background: rgba(255,255,255,0.1); }
        .timeline { border-left: 2px solid var(--primary-color); padding-left: 1.5rem; margin-left: 1rem; position: relative; }
        .timeline-item { position: relative; margin-bottom: 1.5rem; }
        .timeline-item::before { content: ''; position: absolute; left: -1.9rem; top: 0.3rem; width: 12px; height: 12px; border-radius: 50%; background-color: var(--white); border: 2px solid var(--primary-color); }
    </style>
</head>
<body>
    <div class="wrapper">
        <?php include 'inc/sidebar.php'; ?>
        <div id="loader" class="loading-overlay">
            <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status"></div>
            <h4>Active Agent Running...</h4>
            <p class="text-muted">Calculating HHM™ offsets and verifying executive targets.</p>
        </div>
        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="dashboard.php" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left"></i> Back to Dashboard</a>
                <div class="d-flex gap-2">
                    <a href="?id=<?= $company['id'] ?>&refresh=1" class="btn btn-outline-primary" onclick="document.getElementById('loader').style.display='flex'">
                        <i class="bi bi-arrow-clockwise me-1"></i> Run New Query
                    </a>
                </div>
            </div>
            
            <?php if(isset($aiData['error'])): ?>
                <div class="alert alert-danger shadow-sm border-0 border-start border-4 border-danger">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <strong>API Error:</strong> <?= htmlspecialchars($aiData['error']) ?>
                </div>
            <?php endif; ?>

            <div class="console-header shadow-sm">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-white text-primary"><i class="bi bi-robot me-1"></i> AI Sourcing Agent Console</span>
                            <span class="badge bg-light text-dark">Active Agent</span>
                        </div>
                        <h2 class="fw-bold mb-1">Sourcing & EPR Analysis: <?= htmlspecialchars($company['company_name']) ?></h2>
                    </div>
                </div>
                <div class="d-flex gap-3 mt-4">
                    <span class="sourcing-badge rounded-pill"><i class="bi bi-bullseye me-1"></i> Sourcing Sector: <?= htmlspecialchars($aiData['sourcing_sector'] ?? 'Analyzing...') ?></span>
                    <span class="sourcing-badge rounded-pill"><i class="bi bi-battery-charging me-1"></i> Chemistry: <?= htmlspecialchars($aiData['chemistry'] ?? 'Analyzing...') ?></span>
                </div>
                <div class="mt-3">
                    <span class="badge bg-warning text-dark px-3 py-2 fs-6 shadow-sm"><i class="bi bi-tag-fill me-1"></i> EPR Target Classification: <?= htmlspecialchars($aiData['classification'] ?? 'Pending') ?></span>
                </div>
            </div>

            <?php if (isset($aiData['company_overview'])): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom pt-4 pb-3">
                    <h5 class="mb-0 text-primary"><i class="bi bi-building me-2"></i>Company Overview & Operations</h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-3 border-end">
                            <strong class="text-muted small d-block mb-1">Core Business</strong>
                            <span><?= htmlspecialchars($aiData['company_overview']['core_business'] ?? 'N/A') ?></span>
                        </div>
                        <div class="col-md-3 border-end">
                            <strong class="text-muted small d-block mb-1">Key Products</strong>
                            <span><?= htmlspecialchars($aiData['company_overview']['key_products'] ?? 'N/A') ?></span>
                        </div>
                        <div class="col-md-3 border-end">
                            <strong class="text-muted small d-block mb-1">Battery Usage Context</strong>
                            <span><?= htmlspecialchars($aiData['company_overview']['battery_usage'] ?? 'N/A') ?></span>
                        </div>
                        <div class="col-md-3">
                            <strong class="text-muted small d-block mb-1">Operational Scale</strong>
                            <span><?= htmlspecialchars($aiData['company_overview']['scale'] ?? 'N/A') ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0 mb-4 border-start border-4 border-success">
                <div class="card-header bg-transparent border-bottom pt-4 pb-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-success"><i class="bi bi-box-seam me-2"></i>EPR Targets & Material Breakdown</h5>
                    <span class="badge bg-success fs-6 shadow-sm">Total Priority Score: <?= number_format($totalScore, 2) ?></span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Material</th>
                                <th>Target (Tons)</th>
                                <th>Credits Procured</th>
                                <th>Contribution to Priority Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($companyMaterials) > 0): ?>
                                <?php foreach ($companyMaterials as $matName => $matData): ?>
                                <tr>
                                    <td class="ps-4 fw-semibold"><?= htmlspecialchars($matName) ?></td>
                                    <td><?= number_format($matData['target'], 2) ?></td>
                                    <td><?= number_format($matData['credits'], 2) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <strong class="text-dark me-2"><?= number_format($matData['contribution_to_global'], 2) ?></strong>
                                            <small class="text-muted" style="font-size: 0.75rem;" data-bs-toggle="tooltip" title="Z-Score(1-100): <?= number_format($matData['z_score_scaled_100'], 2) ?>">(Global Points)</small>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No material targets recorded for this company.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-7">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-transparent border-bottom pt-4 pb-3">
                            <h5 class="mb-0 text-primary"><i class="bi bi-compass me-2"></i>Strategic Summary</h5>
                        </div>
                        <div class="card-body">
                            <p class="lh-lg text-dark"><?= nl2br(htmlspecialchars($aiData['strategic_summary'] ?? 'Running analysis...')) ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="card shadow-sm border-0 border-start border-4 border-primary h-100 bg-light">
                        <div class="card-header bg-transparent border-bottom pt-4 pb-3">
                            <h5 class="mb-0 text-primary"><i class="bi bi-recycle me-2"></i>MiniMines Closed-Loop Potential</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">If MiniMines secures 100% of this OEM's waste battery feed based on their multi-material targets:</p>
                            <ul class="list-unstyled lh-lg">
                                <?php
                                    $eprCert = $aiData['potential']['epr_certificates'] ?? 'N/A';
                                    $eprCert = is_array($eprCert) ? implode(", ", $eprCert) : $eprCert;
                                    
                                    $recMetals = $aiData['potential']['recovery_metals'] ?? 'N/A';
                                    $recMetals = is_array($recMetals) ? implode(", ", $recMetals) : $recMetals;
                                    
                                    $offsetDep = $aiData['potential']['offset_dependency'] ?? 'N/A';
                                    $offsetDep = is_array($offsetDep) ? implode(", ", $offsetDep) : $offsetDep;
                                ?>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> <strong class="text-dark">EPR Certificates:</strong> <?= htmlspecialchars($eprCert) ?></li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> <strong class="text-dark">High-Purity Metals (HHM™):</strong> <?= htmlspecialchars($recMetals) ?></li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> <strong class="text-dark">HHM™ Efficiency Offset:</strong> <?= htmlspecialchars($offsetDep) ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom pt-4 pb-3">
                    <h5 class="mb-0 text-primary"><i class="bi bi-newspaper me-2"></i>Recent News & Trends Timeline</h5>
                </div>
                <div class="card-body pt-4">
                    <?php if (!empty($aiData['recent_news_trends']) && is_array($aiData['recent_news_trends'])): ?>
                        <div class="timeline">
                            <?php foreach ($aiData['recent_news_trends'] as $news): ?>
                                <div class="timeline-item">
                                    <span class="badge bg-light text-primary border mb-2"><?= htmlspecialchars($news['date'] ?? 'Recent') ?></span>
                                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($news['headline'] ?? '') ?></h6>
                                    <p class="text-muted small mb-0"><?= htmlspecialchars($news['summary'] ?? '') ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center py-4">No recent news or trends found for this company.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-transparent border-bottom pt-4 pb-3">
                    <h5 class="mb-0 text-primary"><i class="bi bi-shield-check me-2"></i>Primary Contact Details (Verified Leads)</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Email</th>
                                    <th>LinkedIn</th>
                                    <th>Verification Link</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($aiData['contacts']) && is_array($aiData['contacts'])): ?>
                                    <?php foreach ($aiData['contacts'] as $contact): ?>
                                    <tr>
                                        <td><span class="fw-semibold"><?= htmlspecialchars($contact['name'] ?? 'N/A') ?></span></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($contact['role'] ?? 'N/A') ?></span></td>
                                        <td>
                                            <?php if(isset($contact['email']) && strtolower($contact['email']) !== 'not publicly available'): ?>
                                                <a href="mailto:<?= htmlspecialchars($contact['email']) ?>"><?= htmlspecialchars($contact['email']) ?></a>
                                            <?php else: ?>
                                                <span class="text-muted fst-italic">Not Publicly Available</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($contact['linkedin']) && strtolower($contact['linkedin']) !== 'not publicly available'): ?>
                                                <a href="<?= htmlspecialchars($contact['linkedin']) ?>" target="_blank" class="text-decoration-none"><i class="bi bi-linkedin me-1"></i>Profile</a>
                                            <?php else: ?>
                                                <span class="text-muted fst-italic">Not Publicly Available</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($contact['proof_source_url'])): ?>
                                                <a href="<?= htmlspecialchars($contact['proof_source_url']) ?>" target="_blank" class="text-decoration-none btn btn-sm btn-outline-secondary"><i class="bi bi-link-45deg"></i> Source</a>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center py-5 text-muted">No verified contacts found by Active Agent.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if (!isset($aiData['sourcing_sector']) && !isset($aiData['error'])): ?>
            document.getElementById('loader').style.display = 'flex';
        <?php endif; ?>
    </script>
</body>
</html>
