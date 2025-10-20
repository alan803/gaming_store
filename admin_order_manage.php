<?php
session_start();

// Ensure admin is authenticated
if (!isset($_SESSION['auth_user_id']) || !isset($_SESSION['auth_admin_id'])) {
    header('Location: login.php');
    exit();
}

include 'connection.php';

$admin_id = $_SESSION['auth_admin_id'];
$admin_username = $_SESSION['auth_username'] ?? 'Admin';

// Handle status update (non-interactive form POST)
$update_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['order_status'])) {
    $order_id = trim($_POST['order_id']);
    $new_status = strtolower(trim($_POST['order_status']));
    $allowed_status = ['processing','shipped','delivered','cancelled'];
    if (in_array($new_status, $allowed_status, true) && $order_id !== '') {
        $stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE order_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('ss', $new_status, $order_id);
            if ($stmt->execute()) {
                $update_message = 'Order updated';
            } else {
                $update_message = 'Failed to update order';
            }
            $stmt->close();
        } else {
            $update_message = 'Server error';
        }
    } else {
        $update_message = 'Invalid status';
    }
}

// Filters
$status_filter = isset($_GET['status']) && $_GET['status'] !== '' ? strtolower(trim($_GET['status'])) : '';
$search_q = isset($_GET['q']) ? trim($_GET['q']) : '';

$params = [];
$where = [];
if ($status_filter !== '') {
    $where[] = 'o.order_status = ?';
    $params[] = $status_filter;
}
if ($search_q !== '') {
$where[] = '(o.order_id LIKE CONCAT("%", ?, "%") OR u.username LIKE CONCAT("%", ?, "%") OR l.email LIKE CONCAT("%", ?, "%"))';
    $params[] = $search_q;
    $params[] = $search_q;
    $params[] = $search_q;
}
$sql = "SELECT o.order_id, o.user_id, o.total_amount, o.payment_status, o.order_status, o.created_at, u.username, l.email
        FROM orders o
        LEFT JOIN users u ON u.user_id = o.user_id
        LEFT JOIN login l ON l.user_id = o.user_id";
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY o.created_at DESC LIMIT 200';

$orders = [];
if (!empty($where)) {
    // build types
    $types = '';
    foreach ($params as $p) { $types .= 's'; }
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) { $orders[] = $row; }
        $stmt->close();
    }
} else {
    $res = $conn->query($sql);
    if ($res) { while ($row = $res->fetch_assoc()) { $orders[] = $row; } }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Admin - NexusGear</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root { --primary:#4f46e5; --primary-light:#6366f1; --dark:#111827; --content:#f9fafb; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color:#f9fafb; margin:0; padding:0; }
        .sidebar { width:280px; height:100vh; position:fixed; left:0; top:0; background:#fff; color:#374151; transition:all .3s ease; display:flex; flex-direction:column; border-right:1px solid #e5e7eb; z-index:1000; overflow-y:auto; }
        .sidebar-collapsed { width:80px; }
        .main-content { margin-left:280px; transition: margin-left .3s ease; min-height:100vh; }
        .main-content.expanded { margin-left:80px; }
        .nav-menu { list-style:none; padding:1rem .75rem; margin:0; flex:1; }
        .nav-link { display:flex; align-items:center; padding:.75rem 1rem; color:#6b7280; text-decoration:none; border-radius:.5rem; transition:all .2s ease; font-size:.9375rem; font-weight:500; position:relative; }
        .nav-link:hover { background:#f3f4f6; color:#111827; }
        .nav-link.active { background:#eef2ff; color:#4f46e5; }
        .nav-link i { width:1.25rem; text-align:center; margin-right:.75rem; font-size:1.125rem; flex-shrink:0; }
        .sidebar::-webkit-scrollbar{width:6px;} .sidebar::-webkit-scrollbar-track{background:transparent;} .sidebar::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:10px;} .sidebar::-webkit-scrollbar-thumb:hover{background:#9ca3af;}
        .hamburger{color:#6b7280; cursor:pointer; transition:all .2s ease; padding:.375rem; border-radius:.375rem;} .hamburger:hover{background:#f3f4f6; color:#111827;}
    </style>
    <script>
        function submitStatus(formId){ document.getElementById(formId).submit(); }
    </script>
    <script>
        function toggleUserMenu(){
            const menu = document.getElementById('userMenu');
            menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
        }
        document.addEventListener('click', (e) => {
            const btn = document.getElementById('userMenuButton');
            const menu = document.getElementById('userMenu');
            if (btn && menu && !btn.contains(e.target) && !menu.contains(e.target)) {
                menu.style.display = 'none';
            }
        });
    </script>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo-container" style="padding:1.25rem 1.5rem; border-bottom:1px solid #e5e7eb;">
            <div style="display:flex; align-items:center; justify-content:space-between;">
                <div style="display:flex; align-items:center; gap:.75rem;">
                    <div style="width:2.5rem; height:2.5rem; background:linear-gradient(135deg, #4f46e5 0%, #6366f1 100%); border-radius:.75rem; display:flex; align-items:center; justify-content:center; box-shadow:0 1px 3px rgba(0,0,0,.1);">
                        <i class="fas fa-shield-alt" style="color:#fff; font-size:1.125rem;"></i>
                    </div>
                    <div class="logo-text">
                        <div style="font-weight:600; color:#111827; font-size:1.125rem; line-height:1.2;">NexusGear</div>
                        <div style="font-size:.75rem; color:#6b7280;">Admin Panel</div>
                    </div>
                </div>
                <button id="toggleSidebar" class="hamburger"><i class="fas fa-bars"></i></button>
            </div>
        </div>

        <ul class="nav-menu">
            <li class="nav-item"><a href="admindashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i><span class="nav-text">Dashboard</span></a></li>
            <li class="nav-item"><a href="add_product.php" class="nav-link"><i class="fas fa-box"></i><span class="nav-text">Add Product</span></a></li>
            <li class="nav-item"><a href="manage_product.php" class="nav-link"><i class="fas fa-cogs"></i><span class="nav-text">Manage Products</span></a></li>
            <li class="nav-item"><a href="add_category.php" class="nav-link"><i class="fas fa-tags"></i><span class="nav-text">Categories</span></a></li>
            <li class="nav-item"><a href="admin_order_manage.php" class="nav-link active"><i class="fas fa-shopping-cart"></i><span class="nav-text">Orders</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Navigation -->
        <header style="background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.1); border-bottom:1px solid #e5e7eb;">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:1rem 1.5rem;">
                <div style="display:flex; align-items:center; gap:1rem;">
                    <h1 style="font-size:1.25rem; font-weight:600; color:#1f2937; margin:0;">Manage Orders</h1>
                </div>
                <div style="display:flex; align-items:center; gap:1rem;">
                    <div style="position:relative;">
                        <button id="userMenuButton" onclick="toggleUserMenu()" style="display:flex; align-items:center; gap:.5rem; border:none; background:transparent; cursor:pointer;">
                            <div style="width:2rem; height:2rem; border-radius:9999px; background:#eef2ff; display:flex; align-items:center; justify-content:center; color:#4f46e5; font-weight:500;">
                                <?php echo strtoupper(substr($admin_username, 0, 1)); ?>
                            </div>
                            <span style="font-size:.875rem; font-weight:500; color:#374151;"><?php echo htmlspecialchars($admin_username); ?></span>
                            <i class="fas fa-chevron-down" style="font-size:.75rem; color:#6b7280;"></i>
                        </button>
                        <div id="userMenu" style="display:none; position:absolute; right:0; margin-top:.5rem; width:12rem; background:#fff; border-radius:.375rem; box-shadow:0 10px 15px -3px rgba(0,0,0,.1); padding:.25rem 0; z-index:50; border:1px solid #e5e7eb;">
                            <form action="logout.php" method="post" style="width:100%; margin:0;">
                                <button type="submit" style="width:100%; text-align:left; padding:.5rem 1rem; font-size:.875rem; color:#dc2626; border:none; background:transparent; cursor:pointer;" onmouseover="this.style.backgroundColor='#fef2f2'" onmouseout="this.style.backgroundColor='transparent'">
                                    <i class="fas fa-sign-out-alt" style="margin-right:.5rem;"></i>Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <main style="padding:1.5rem;">
            <div style="background:#fff; border-radius:.75rem; box-shadow:0 1px 3px rgba(0,0,0,.1); border:1px solid #f3f4f6;">
                <div style="padding:1rem 1.5rem; border-bottom:1px solid #f3f4f6; display:flex; gap:1rem; align-items:center; justify-content:space-between; flex-wrap:wrap;">
                    <h3 style="font-size:1.125rem; font-weight:500; color:#111827; margin:0;">Orders</h3>
                    <form method="get" style="display:flex; gap:.5rem; align-items:center;">
                        <input type="text" name="q" value="<?php echo htmlspecialchars($search_q); ?>" placeholder="Search order/user/email" style="padding:.5rem .75rem; border:1px solid #d1d5db; border-radius:.375rem; font-size:.875rem;">
                        <select name="status" style="padding:.5rem .75rem; border:1px solid #d1d5db; border-radius:.375rem; font-size:.875rem;">
                            <option value="">All statuses</option>
                            <?php $opts=['processing','shipped','delivered','cancelled']; foreach($opts as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo $status_filter===$opt?'selected':''; ?>><?php echo ucfirst($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" style="padding:.5rem .9rem; background:#4f46e5; color:#fff; border:none; border-radius:.375rem; font-size:.875rem;">Filter</button>
                    </form>
                </div>
                <?php if ($update_message): ?>
                    <div style="padding: .75rem 1.5rem; background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; margin:1rem 1.5rem; border-radius:.5rem; font-size:.875rem;">
                        <?php echo htmlspecialchars($update_message); ?>
                    </div>
                <?php endif; ?>
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead style="background:#f9fafb;">
                            <tr>
                                <th style="padding:.75rem 1.5rem; text-align:left; font-size:.75rem; font-weight:500; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Order ID</th>
                                <th style="padding:.75rem 1.5rem; text-align:left; font-size:.75rem; font-weight:500; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Customer</th>
                                <th style="padding:.75rem 1.5rem; text-align:left; font-size:.75rem; font-weight:500; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Total</th>
                                <th style="padding:.75rem 1.5rem; text-align:left; font-size:.75rem; font-weight:500; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Payment</th>
                                <th style="padding:.75rem 1.5rem; text-align:left; font-size:.75rem; font-weight:500; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Status</th>
                                <th style="padding:.75rem 1.5rem; text-align:left; font-size:.75rem; font-weight:500; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Placed</th>
                                <th style="padding:.75rem 1.5rem; text-align:right; font-size:.75rem; font-weight:500; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Action</th>
                            </tr>
                        </thead>
                        <tbody style="background:#fff;">
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="7" style="padding:1rem 1.5rem; text-align:center; color:#6b7280; font-size:.875rem;">No orders found.</td>
                                </tr>
                            <?php else: foreach ($orders as $o): ?>
                                <tr style="border-bottom:1px solid #f3f4f6;">
                                    <td style="padding:1rem 1.5rem; white-space:nowrap; font-size:.875rem; font-weight:500; color:#111827;">#<?php echo htmlspecialchars($o['order_id']); ?></td>
                                    <td style="padding:1rem 1.5rem; white-space:nowrap; font-size:.875rem; color:#6b7280;">
                                        <?php echo htmlspecialchars($o['username'] ?: 'User '.$o['user_id']); ?><br>
                                        <span style="font-size:.75rem; color:#9ca3af;"><?php echo htmlspecialchars($o['email'] ?? ''); ?></span>
                                    </td>
                                    <td style="padding:1rem 1.5rem; white-space:nowrap; font-size:.875rem; color:#111827;">$<?php echo number_format((float)$o['total_amount'], 2); ?></td>
                                    <td style="padding:1rem 1.5rem; white-space:nowrap;">
                                        <span style="padding:.125rem .5rem; display:inline-flex; font-size:.75rem; font-weight:600; border-radius:9999px; background-color: <?php echo strtolower($o['payment_status'])==='paid' ? '#ecfdf5' : '#fff7ed'; ?>; color: <?php echo strtolower($o['payment_status'])==='paid' ? '#065f46' : '#92400e'; ?>;">
                                            <?php echo htmlspecialchars(ucfirst($o['payment_status'])); ?>
                                        </span>
                                    </td>
                                    <td style="padding:1rem 1.5rem; white-space:nowrap;">
                                        <form id="f_<?php echo htmlspecialchars($o['order_id']); ?>" method="post" style="display:flex; gap:.5rem; align-items:center;">
                                            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($o['order_id']); ?>" />
                                            <select name="order_status" style="padding:.4rem .6rem; border:1px solid #d1d5db; border-radius:.375rem; font-size:.8125rem;">
                                                <?php $opts=['processing','shipped','delivered','cancelled']; foreach($opts as $opt): ?>
                                                    <option value="<?php echo $opt; ?>" <?php echo strtolower($o['order_status'])===$opt?'selected':''; ?>><?php echo ucfirst($opt); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" style="padding:.4rem .8rem; background:#4f46e5; color:#fff; border:none; border-radius:.375rem; font-size:.8125rem;">Update</button>
                                        </form>
                                    </td>
                                    <td style="padding:1rem 1.5rem; white-space:nowrap; font-size:.875rem; color:#6b7280;"><?php echo date('M j, Y H:i', strtotime($o['created_at'])); ?></td>
                                    <td style="padding:1rem 1.5rem; white-space:nowrap; text-align:right; font-size:.875rem; font-weight:500;">
                                        <a href="orders.php?order_id=<?php echo urlencode($o['order_id']); ?>" style="color:#4f46e5; text-decoration:none;">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Sidebar toggle (persist in localStorage like dashboard)
        const toggleSidebar = document.getElementById('toggleSidebar');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        let isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) { sidebar.classList.add('sidebar-collapsed'); mainContent.classList.add('expanded'); }
        toggleSidebar.addEventListener('click', () => {
            isCollapsed = !isCollapsed;
            sidebar.classList.toggle('sidebar-collapsed');
            mainContent.classList.toggle('expanded');
            localStorage.setItem('sidebarCollapsed', isCollapsed);
        });
    </script>
</body>
</html>


