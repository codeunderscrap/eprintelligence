<?php
include 'config.php';
use Dompdf\Dompdf;

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if (!isset($_GET['id'])) {
    die("No company specified.");
}

$stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([$_GET['id']]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$company) {
    die("Company not found.");
}

$html = "
<html>
<head>
    <style>
        body { font-family: sans-serif; }
        h1 { color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f4f4f4; }
    </style>
</head>
<body>
    <h1>EPR Report: " . htmlspecialchars($company['company_name']) . "</h1>
    <p>Generated on: " . date('Y-m-d H:i:s') . "</p>
    <table>
        <tr>
            <th>Registration Number</th>
            <td>" . htmlspecialchars($company['registration_number']) . "</td>
        </tr>
        <tr>
            <th>Target (Tons)</th>
            <td>" . number_format($company['target_tons'], 2) . "</td>
        </tr>
        <tr>
            <th>Credits</th>
            <td>" . number_format($company['credits'], 2) . "</td>
        </tr>
    </table>
</body>
</html>
";

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("EPR_Report_" . preg_replace('/[^A-Za-z0-9\-]/', '_', $company['company_name']) . ".pdf", ["Attachment" => false]);
?>
