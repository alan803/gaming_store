<?php
session_start();

// Ensure admin is authenticated
if (!isset($_SESSION['auth_user_id']) || !isset($_SESSION['auth_admin_id'])) {
    header('Location: login.php');
    exit();
}

include 'connection.php';

$admin_username = $_SESSION['auth_username'] ?? 'Admin';

// Handle actions: update (price/stock/category/name), delete
$action_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $pid = (int) $_POST['delete_id'];
        $stmt = $conn->prepare('DELETE FROM products WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $pid);
            $ok = $stmt->execute();
            $stmt->close();
            $action_message = $ok ? 'Product deleted' : 'Failed to delete product';
        } else {
            $action_message = 'Server error (delete)';
        }
    } elseif (isset($_POST['product_id'])) {
        $pid = (int) $_POST['product_id'];

        // Fetch current values
        $curStmt = $conn->prepare('SELECT name, price, stock, category_id FROM products WHERE id = ? LIMIT 1');
        if (!$curStmt) {
            $action_message = 'Server error (read current)';
        } else {
            $curStmt->bind_param('i', $pid);
            $curStmt->execute();
            $cur = $curStmt->get_result()->fetch_assoc();
            $curStmt->close();

            if ($cur) {
                $set = [];
                $types = '';
                $vals = [];

                if (array_key_exists('name', $_POST)) {
                    $name = trim((string)$_POST['name']);
                    if ($name !== '' && $name !== (string)$cur['name']) {
                        $set[] = 'name = ?';
                        $types .= 's';
                        $vals[] = $name;
                    }
                }

                if (array_key_exists('price', $_POST)) {
                    $price = (float) $_POST['price'];
                    if ((float)$cur['price'] !== $price) {
                        $set[] = 'price = ?';
                        $types .= 'd';
                        $vals[] = $price;
                    }
                }

                if (array_key_exists('stock', $_POST)) {
                    $stock = (int) $_POST['stock'];
                    if ((int)$cur['stock'] !== $stock) {
                        $set[] = 'stock = ?';
                        $types .= 'i';
                        $vals[] = $stock;
                    }
                }

                if (array_key_exists('category_id', $_POST)) {
                    $category_id_raw = $_POST['category_id'];
                    if ($category_id_raw !== '' && is_numeric($category_id_raw) && (int)$category_id_raw > 0) {
                        $cid = (int) $category_id_raw;
                        if ((int)$cur['category_id'] !== $cid) {
                            $chk = $conn->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
                            if ($chk) {
                                $chk->bind_param('i', $cid);
                                $chk->execute();
                                $res = $chk->get_result();
                                if ($res && $res->num_rows === 1) {
                                    $set[] = 'category_id = ?';
                                    $types .= 'i';
                                    $vals[] = $cid;
                                }
                                $chk->close();
                            }
                        }
                    }
                }

                if (empty($set)) {
                    $action_message = 'No changes detected';
                } else {
                    $sql = 'UPDATE products SET ' . implode(', ', $set) . ' WHERE id = ?';
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $types .= 'i';
                        $vals[] = $pid;
                        $stmt->bind_param($types, ...$vals);
                        $ok = $stmt->execute();
                        $stmt->close();
                        $action_message = $ok ? 'Product updated' : 'Failed to update';
                    } else {
                        $action_message = 'Server error (update)';
                    }
                }
            } else {
                $action_message = 'Product not found';
            }
        }
    }
}

// Filters
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$cat = isset($_GET['category']) ? (int) $_GET['category'] : 0;

// Fetch categories for filter/select
$categories = [];
$cres = $conn->query('SELECT id, name FROM categories ORDER BY name ASC');
if ($cres) { while ($r = $cres->fetch_assoc()) { $categories[] = $r; } }

// Build product query
$where = [];
$params = [];
$types = '';
if ($q !== '') { $where[] = '(p.name LIKE CONCAT("%", ?, "%") OR p.description LIKE CONCAT("%", ?, "%"))'; $params[] = $q; $params[] = $q; $types .= 'ss'; }
if ($cat > 0) { $where[] = 'p.category_id = ?'; $params[] = $cat; $types .= 'i'; }

$sql = 'SELECT p.id, p.name, p.price, p.stock, p.category_id, p.image_name, c.name AS category_name, p.description
        FROM products p LEFT JOIN categories c ON c.id = p.category_id';
if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }
$sql .= ' ORDER BY p.id DESC LIMIT 300';

$products = [];
if (!empty($where)) {
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) { $products[] = $row; }
        $stmt->close();
    }
} else {
    $res = $conn->query($sql);
    if ($res) { while ($row = $res->fetch_assoc()) { $products[] = $row; } }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Admin - NexusGear</title>
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
        .hamburger{color:#6b7280; cursor:pointer; transition:all .2s ease; padding:.375rem; border-radius:.375rem;} .hamburger:hover{background:#f3f4f6; color:#111827;}
        .btn { padding:.45rem .9rem; border-radius:.375rem; font-size:.8125rem; }
        .btn-primary { background:#4f46e5; color:#fff; }
        .btn-danger { background:#ef4444; color:#fff; }
        .input { padding:.5rem .75rem; border:1px solid #d1d5db; border-radius:.375rem; font-size:.875rem; }
        .select { padding:.5rem .75rem; border:1px solid #d1d5db; border-radius:.375rem; font-size:.875rem; }
    </style>
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
            <li class="nav-item"><a href="manage_product.php" class="nav-link active"><i class="fas fa-cogs"></i><span class="nav-text">Manage Products</span></a></li>
            <li class="nav-item"><a href="add_category.php" class="nav-link"><i class="fas fa-tags"></i><span class="nav-text">Categories</span></a></li>
            <li class="nav-item"><a href="admin_order_manage.php" class="nav-link"><i class="fas fa-shopping-cart"></i><span class="nav-text">Orders</span></a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Navigation -->
        <header style="background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.1); border-bottom:1px solid #e5e7eb;">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:1rem 1.5rem;">
                <div style="display:flex; align-items:center; gap:1rem;">
                    <h1 style="font-size:1.25rem; font-weight:600; color:#1f2937; margin:0;">Manage Products</h1>
                </div>
                <div style="display:flex; align-items:center; gap:1rem;">
                    <a href="add_product.php" class="btn btn-primary" style="text-decoration:none;">Add Product</a>
                    <div style="position:relative;">
                        <button id="userMenuButton" style="display:flex; align-items:center; gap:.5rem; border:none; background:transparent; cursor:pointer;" onclick="document.getElementById('userMenu').style.display = document.getElementById('userMenu').style.display==='none'?'block':'none'">
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
                <div style="padding:1rem 1.5rem; border-bottom:1px solid #f3f4f6; display:flex; gap:.75rem; align-items:center; justify-content:space-between; flex-wrap:wrap;">
                    <form method="get" style="display:flex; gap:.5rem; align-items:center;">
                        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" class="input" placeholder="Search name/description">
                        <select name="category" class="select">
                            <option value="0">All categories</option>
                            <?php foreach($categories as $c): ?>
                                <option value="<?php echo (int)$c['id']; ?>" <?php echo $cat===(int)$c['id']?'selected':''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </form>
                    <?php if ($action_message): ?>
                        <div style="padding:.5rem .75rem; background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; border-radius:.375rem; font-size:.8125rem;">
                            <?php echo htmlspecialchars($action_message); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse;">
                        <thead style="background:#f9fafb;">
                            <tr>
                                <th style="padding:.75rem 1.5rem; text-align:left; font-size:.75rem; font-weight:500; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Product</th>
                                <th style="padding:.75rem 1.5rem; text-align:left; font-size:.75rem; font-weight:500; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Category</th>
                                <th style="padding:.75rem 1.5rem; text-align:left; font-size:.75rem; font-weight:500; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Price</th>
                                <th style="padding:.75rem 1.5rem; text-align:left; font-size:.75rem; font-weight:500; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Stock</th>
                                <th style="padding:.75rem 1.5rem; text-align:right; font-size:.75rem; font-weight:500; color:#6b7280; text-transform:uppercase; letter-spacing:.05em;">Actions</th>
                            </tr>
                        </thead>
                        <tbody style="background:#fff;">
                            <?php if (empty($products)): ?>
                                <tr><td colspan="5" style="padding:1rem 1.5rem; text-align:center; color:#6b7280; font-size:.875rem;">No products found.</td></tr>
                            <?php else: foreach($products as $p): ?>
                                <tr style="border-bottom:1px solid #f3f4f6;">
                                    <td style="padding:1rem 1.5rem; font-size:.875rem; color:#111827;">
                                        <div style="display:flex; align-items:center; gap:.75rem;">
                                            <img src="<?php echo htmlspecialchars('uploads/products/' . ($p['image_name'] ?? '')); ?>" alt="" style="width:48px; height:48px; object-fit:cover; border-radius:.5rem; border:1px solid #e5e7eb;">
                                            <form method="post" style="display:flex; flex-direction:column; gap:.25rem;">
                                                <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">
                                                <input class="input" name="name" value="<?php echo htmlspecialchars($p['name']); ?>" style="max-width:320px;">
                                            </form>
                                        </div>
                                    </td>
                                    <td style="padding:1rem 1.5rem; font-size:.875rem; color:#6b7280;">
                                        <form method="post" id="cat_<?php echo (int)$p['id']; ?>">
                                            <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">
                                            <select class="select" name="category_id" onchange="document.getElementById('cat_<?php echo (int)$p['id']; ?>').submit()">
                                                <?php foreach($categories as $c): ?>
                                                    <option value="<?php echo (int)$c['id']; ?>" <?php echo (int)$p['category_id']===(int)$c['id']?'selected':''; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </td>
                                    <td style="padding:1rem 1.5rem; font-size:.875rem; color:#111827;">
                                        <form method="post" id="price_<?php echo (int)$p['id']; ?>" style="display:flex; gap:.5rem; align-items:center;">
                                            <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">
                                            <input class="input" name="price" type="number" step="0.01" min="0" value="<?php echo number_format((float)$p['price'], 2, '.', ''); ?>" style="width:120px;">
                                            <button class="btn btn-primary" type="submit">Save</button>
                                        </form>
                                    </td>
                                    <td style="padding:1rem 1.5rem; font-size:.875rem; color:#111827;">
                                        <form method="post" id="stock_<?php echo (int)$p['id']; ?>" style="display:flex; gap:.5rem; align-items:center;">
                                            <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">
                                            <input class="input" name="stock" type="number" step="1" min="0" value="<?php echo (int)$p['stock']; ?>" style="width:100px;">
                                            <button class="btn btn-primary" type="submit">Save</button>
                                        </form>
                                    </td>
                                    <td style="padding:1rem 1.5rem; text-align:right;">
                                        <form method="post" onsubmit="return confirm('Delete this product?');" style="display:inline-block;">
                                            <input type="hidden" name="delete_id" value="<?php echo (int)$p['id']; ?>">
                                            <button class="btn btn-danger" type="submit">Delete</button>
                                        </form>
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
        // Sidebar toggle with persistence
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
        document.addEventListener('click', (e) => {
            const btn = document.getElementById('userMenuButton');
            const menu = document.getElementById('userMenu');
            if (btn && menu && !btn.contains(e.target) && !menu.contains(e.target)) { menu.style.display='none'; }
        });
    </script>
</body>
</html>


