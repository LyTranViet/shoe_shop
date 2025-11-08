<?php
// --- Secure include ---
if (!isset($db)) {
    header('Location: index.php');
    exit;
}

// --- Date Filtering ---
$currentYear = date('Y');
$currentMonth = date('m');
$filterYear = (int)($_GET['year'] ?? $currentYear);
$filterMonth = (int)($_GET['month'] ?? $currentMonth);

$startDate = date('Y-m-d H:i:s', mktime(0, 0, 0, $filterMonth, 1, $filterYear));
$endDate   = date('Y-m-d H:i:s', mktime(23, 59, 59, $filterMonth + 1, 0, $filterYear));

// --- Statistics ---
$whereClause = "AND created_at BETWEEN ? AND ?";

// Revenue
$stmt = $db->prepare("SELECT SUM(total_amount) FROM orders WHERE status_id = 3 $whereClause");
$stmt->execute([$startDate, $endDate]);
$periodRevenue = $stmt->fetchColumn() ?: 0;

// Orders
$stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE 1=1 $whereClause");
$stmt->execute([$startDate, $endDate]);
$periodOrders = $stmt->fetchColumn() ?: 0;

// Customers
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT u.id)
    FROM users u
    JOIN user_roles ur ON u.id = ur.user_id
    WHERE ur.role_id = 2 AND u.created_at BETWEEN ? AND ?
");
$stmt->execute([$startDate, $endDate]);
$periodCustomers = $stmt->fetchColumn() ?: 0;

// --- Charts ---
// Daily Revenue
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $filterMonth, $filterYear);
$dailyRevenueLabels = range(1, $daysInMonth);
$dailyRevenueData = array_fill(0, $daysInMonth, 0);

$stmt = $db->prepare("
    SELECT DAY(created_at) as day, SUM(total_amount) as total
    FROM orders
    WHERE status_id = 3 $whereClause
    GROUP BY DAY(created_at)
");
$stmt->execute([$startDate, $endDate]);
foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $day => $total) {
    $dailyRevenueData[$day - 1] = (float)$total;
}

// Top Products
$stmt = $db->prepare("
    SELECT p.name, SUM(oi.quantity) as sold
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status_id = 3 AND o.created_at BETWEEN ? AND ?
    GROUP BY p.name
    ORDER BY sold DESC
    LIMIT 5
");
$stmt->execute([$startDate, $endDate]);
$topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$topProductsLabels = array_column($topProducts, 'name');
$topProductsData   = array_column($topProducts, 'sold');

// Order Status
$stmt = $db->prepare("
    SELECT os.name, COUNT(o.id) as count
    FROM orders o
    JOIN order_status os ON o.status_id = os.id
    WHERE o.created_at BETWEEN ? AND ?
    GROUP BY os.name
");
$stmt->execute([$startDate, $endDate]);
$orderStatuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
$orderStatusLabels = array_column($orderStatuses, 'name');
$orderStatusData   = array_column($orderStatuses, 'count');

// Recent Orders
$stmt = $db->prepare("
    SELECT o.id, o.total_amount, o.created_at, os.name as status, u.name as customer
    FROM orders o
    LEFT JOIN order_status os ON o.status_id = os.id
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.created_at BETWEEN ? AND ?
    ORDER BY o.created_at DESC
");
$stmt->execute([$startDate, $endDate]);
$recent = $stmt->fetchAll();
?>

<!-- === DASHBOARD === -->
<div class="dashboard-container">
    <header class="dashboard-header">
        <div>
            <h1>📊 Sales Dashboard</h1>
            <p>Period: <strong><?= date('F Y', mktime(0, 0, 0, $filterMonth, 1, $filterYear)) ?></strong></p>
        </div>
        <form method="get" action="index.php" class="filter-form">
            <input type="hidden" name="page" value="dashboard">
            <select name="month">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= ($m == $filterMonth) ? 'selected' : '' ?>>
                        <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                    </option>
                <?php endfor; ?>
            </select>
            <select name="year">
                <?php for ($y = $currentYear; $y >= $currentYear - 5; $y--): ?>
                    <option value="<?= $y ?>" <?= ($y == $filterYear) ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <button class="btn">🔍 Filter</button>
        </form>
    </header>

    <!-- === STATS CARDS === -->
    <section class="stats-grid">
        <div class="stat-card gradient-blue">
            <div class="stat-icon">💰</div>
            <div><h4>Revenue</h4><p><?= number_format($periodRevenue, 0) ?>₫</p></div>
        </div>
        <div class="stat-card gradient-green">
            <div class="stat-icon">🛒</div>
            <div><h4>Orders</h4><p><?= number_format($periodOrders) ?></p></div>
        </div>
        <div class="stat-card gradient-purple">
            <div class="stat-icon">👥</div>
            <div><h4>Customers</h4><p><?= number_format($periodCustomers) ?></p></div>
        </div>
    </section>

    <!-- === CHARTS GRID === -->
    <section class="charts-grid">
        <div class="chart-card">
            <h3>📈 Daily Revenue</h3>
            <canvas id="revenueChart"></canvas>
        </div>
        <div class="chart-card">
            <h3>🧾 Order Status</h3>
            <canvas id="statusChart"></canvas>
        </div>
        <div class="chart-card">
            <h3>🔥 Top 5 Products</h3>
            <canvas id="topProductsChart"></canvas>
        </div>
        <div class="chart-card">
            <h3>🕒 Recent Orders</h3>
            <div class="recent-orders-table-wrapper">
                <table class="modern-table">
                    <thead><tr><th>#</th><th>Customer</th><th>Amount</th><th>Date</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent as $r): ?>
                        <tr>
                            <td>#<?= $r['id'] ?></td>
                            <td><?= htmlspecialchars($r['customer'] ?? 'Guest') ?></td>
                            <td><?= number_format($r['total_amount'], 0) ?>₫</td>
                            <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                            <td><span class="status <?= strtolower(str_replace(' ', '-', $r['status'] ?? '')) ?>">
                                <?= htmlspecialchars(ucfirst($r['status'] ?? 'N/A')) ?>
                            </span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<!-- === CHART.JS === -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const revenueData = <?= json_encode($dailyRevenueData) ?>;
const revenueLabels = <?= json_encode($dailyRevenueLabels) ?>;
const statusLabels = <?= json_encode($orderStatusLabels) ?>;
const statusData = <?= json_encode($orderStatusData) ?>;
const topLabels = <?= json_encode($topProductsLabels) ?>;
const topData = <?= json_encode($topProductsData) ?>;

// Revenue Chart
new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: { labels: revenueLabels, datasets: [{
        label: 'Revenue',
        data: revenueData,
        borderColor: '#3b82f6',
        backgroundColor: 'rgba(59,130,246,0.15)',
        fill: true,
        tension: 0.35
    }]},
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});

// Order Status
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: { labels: statusLabels, datasets: [{
        data: statusData,
        backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444']
    }]},
    options: { plugins: { legend: { position: 'bottom' } } }
});

// Top Products
new Chart(document.getElementById('topProductsChart'), {
    type: 'bar',
    data: { labels: topLabels, datasets: [{
        label: 'Units Sold',
        data: topData,
        backgroundColor: '#a855f7'
    }]},
    options: { indexAxis: 'y', plugins: { legend: { display: false } } }
});
</script>

<style>
body {
    font-family: 'Inter', sans-serif;
    background: #f8fafc;
    color: #1e293b;
    margin: 0;
    padding: 0px;
}
.dashboard-container {
    max-width: 1200px;
    margin: auto;
}
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}
.dashboard-header h1 {
    font-size: 1.8rem;
    font-weight: 600;
    color: #111827;
}
.filter-form select, .btn {
    padding: 8px 10px;
    margin-left: 5px;
    border-radius: 8px;
    border: 1px solid #d1d5db;
}
.btn {
    background: #3b82f6;
    color: white;
    cursor: pointer;
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}
.stat-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 18px;
    border-radius: 16px;
    color: white;
    font-weight: 500;
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}
.stat-card:hover { transform: translateY(-4px); }
.stat-icon { font-size: 2rem; }
.gradient-blue { background: linear-gradient(135deg, #60a5fa, #2563eb); }
.gradient-green { background: linear-gradient(135deg, #34d399, #059669); }
.gradient-purple { background: linear-gradient(135deg, #c084fc, #7c3aed); }

.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
    gap: 1.5rem;
}
.chart-card {
    background: white;
    border-radius: 16px;
    padding: 16px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.05);
}
.chart-card h3 { margin-bottom: 10px; }
canvas { width: 100%; height: 280px; }

.modern-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}
.modern-table th, .modern-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}
.modern-table th {
    background: #f1f5f9;
    font-weight: 600;
}
.status {
    padding: 4px 8px;
    border-radius: 8px;
    font-size: 0.85rem;
}
.status.completed { background:#dcfce7; color:#166534; }
.status.pending   { background:#fef9c3; color:#854d0e; }
.status.cancelled { background:#fee2e2; color:#991b1b; }
.status.shipping  { background:#dbeafe; color:#1e40af; }

.recent-orders-table-wrapper {
    max-height: 300px; /* Giới hạn chiều cao */
    overflow-y: auto; /* Thêm thanh cuộn dọc khi cần */
}
</style>
