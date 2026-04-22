<?php
session_start();

/* ========================================================
   1. SAAS CONFIGURATION & AUTHENTICATION 
   ======================================================== */
// Master Accounts Database (In a real SaaS, this is MySQL, but we use PHP arrays for portability here)
$accounts = [
    // Super Admin (You - Can create resellers)
    'master' => ['password' => 'superadmin2024', 'role' => 'SUPER_ADMIN'],
    
    // Reseller 1 (Agency client - Can manage their own weddings)
    'reseller1' => ['password' => 'agency2024', 'role' => 'RESELLER', 'limit' => 5],
    
    // Direct Client (Just the couple managing their RSVP)
    'emma_james' => ['password' => 'wedding2024', 'role' => 'CLIENT', 'wedding_id' => 'wed_default']
];

// Handle Login
if (isset($_POST['username']) && isset($_POST['password'])) {
    $usr = htmlspecialchars($_POST['username']);
    $pwd = htmlspecialchars($_POST['password']);
    
    if (array_key_exists($usr, $accounts) && $accounts[$usr]['password'] === $pwd) {
        $_SESSION['user'] = $usr;
        $_SESSION['role'] = $accounts[$usr]['role'];
        if(isset($accounts[$usr]['wedding_id'])) $_SESSION['wedding_id'] = $accounts[$usr]['wedding_id'];
    } else {
        $error_msg = "Invalid Credentials!";
    }
}

// Handle Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit();
}

/* ========================================================
   2. DATA MANAGEMENT (Multi-Tenant Logic)
   ======================================================== */
// Helper function to read/write JSON safely
function getDb($filename) {
    if(file_exists($filename)) return json_decode(file_get_contents($filename), true) ?: [];
    return [];
}
function saveDb($filename, $data) {
    $fp = fopen($filename, 'c+');
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0); rewind($fp);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

// For Demo: Assume "wed_default" is the wedding we are managing
$active_wedding = 'data.json'; 
$rsvps = getDb($active_wedding);

// Calculate Stats for Active Wedding
$stats = ['total' => count($rsvps), 'attending' => 0, 'guests' => 0, 'declined' => 0];
foreach($rsvps as $r) {
    if(isset($r['attendance']) && $r['attendance'] === "Joyfully Accept") {
        $stats['attending']++;
        $stats['guests'] += (int)($r['guests'] ?? 0);
    } else { $stats['declined']++; }
}

/* ========================================================
   3. LOGIN SCREEN (If not logged in)
   ======================================================== */
if (!isset($_SESSION['user'])) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>SaaS Dashboard Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Montserrat', sans-serif; }
        body { background: #020408; height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; color: #fff;}
        .login-box { background: rgba(255,255,255,0.03); padding: 50px 40px; border-radius: 20px; border: 1px solid rgba(212, 163, 115, 0.2); backdrop-filter: blur(20px); text-align: center; width: 100%; max-width: 400px; box-shadow: 0 30px 60px rgba(0,0,0,0.8); }
        .logo { font-size: 1.5rem; color: #d4a373; letter-spacing: 5px; margin-bottom: 5px; font-weight: 600; text-transform: uppercase; }
        .sub { font-size: 0.8rem; color: #888; letter-spacing: 2px; margin-bottom: 40px; }
        input { width: 100%; padding: 15px; margin-bottom: 20px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #fff; font-size: 1rem; transition: border 0.3s; }
        input:focus { border-color: #d4a373; outline: none; }
        button { width: 100%; padding: 15px; border-radius: 8px; border: none; background: #d4a373; color: #020408; font-weight: bold; cursor: pointer; transition: 0.3s; font-size: 1rem; }
        button:hover { background: #ebd197; transform: translateY(-3px); box-shadow: 0 10px 20px rgba(212, 163, 115, 0.3); }
        .err { color: #ff4b59; font-size: 0.85rem; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="logo">Opal Events</div>
        <div class="sub">GLOBAL DASHBOARD</div>
        <?php if(isset($error_msg)) echo "<div class='err'>$error_msg</div>"; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="Username (e.g. master)" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Sign In</button>
        </form>
        <p style="margin-top: 30px; font-size: 0.75rem; color: #444;">Role-based SaaS System V1.0</p>
    </div>
</body>
</html>
<?php exit(); } ?>

<!-- ========================================================
     4. THE MAIN DASHBOARD (SuperAdmin, Reseller, Client)
     ======================================================== -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Opal Panel - <?php echo $_SESSION['role']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Montserrat', sans-serif; }
        body { background: #050a11; color: #cbd5e1; display: flex; height: 100vh; overflow: hidden; }
        
        /* 1. Glassmorphism Sidebar */
        .sidebar { width: 280px; background: rgba(2,4,8,0.95); border-right: 1px solid rgba(212, 163, 115, 0.1); display: flex; flex-direction: column; }
        .sb-head { padding: 30px; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: center; }
        .sb-head h2 { color: #d4a373; font-weight: 600; letter-spacing: 4px; font-size: 1.2rem; }
        .sb-head span { font-size: 0.7rem; color: #10b981; background: rgba(16, 185, 129, 0.1); padding: 3px 8px; border-radius: 20px; letter-spacing: 1px; display: inline-block; margin-top: 10px;}
        
        .nav-links { flex: 1; padding: 20px 0; }
        .nav-btn { padding: 18px 30px; color: #888; text-decoration: none; display: block; border-left: 3px solid transparent; transition: 0.3s; cursor: pointer; }
        .nav-btn:hover, .nav-btn.active { color: #fff; background: rgba(212,163,115,0.05); border-left-color: #d4a373; }
        .nav-btn i { margin-right: 15px; width: 20px; text-align: center; color: #d4a373; }
        
        .sb-foot { padding: 20px; border-top: 1px solid rgba(255,255,255,0.05); }
        .logout-btn { display: block; text-align: center; padding: 12px; background: rgba(255, 75, 89, 0.1); color: #ff4b59; text-decoration: none; border-radius: 8px; transition: 0.3s; }
        .logout-btn:hover { background: #ff4b59; color: white; }

        /* 2. Main Content Area */
        .main { flex: 1; padding: 40px; overflow-y: auto; position: relative; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        .top-bar h1 { color: #fff; font-size: 1.8rem; font-weight: 500; }
        .user-tag { background: rgba(255,255,255,0.05); padding: 10px 20px; border-radius: 30px; font-size: 0.85rem; border: 1px solid rgba(255,255,255,0.1); }

        /* View Switching logic */
        .view-section { display: none; animation: slideUp 0.5s ease; }
        .view-section.active { display: block; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        /* 3. Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); padding: 30px; border-radius: 15px; text-align: center; transition: transform 0.3s; border-bottom: 2px solid #d4a373; }
        .stat-card:hover { transform: translateY(-5px); background: rgba(212,163,115,0.05); }
        .s-val { font-size: 3rem; color: #fff; font-weight: 300; margin: 10px 0; font-family: 'Cinzel', serif;}
        .s-lbl { color: #888; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 2px; }

        /* 4. Elegant Glass Table */
        .table-wrap { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 15px; overflow: hidden; padding: 20px; }
        .t-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        input.search { padding: 12px 20px; border-radius: 30px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); color: white; width: 300px; outline: none; }
        input.search:focus { border-color: #d4a373; }
        
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { color: #d4a373; font-weight: 500; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 2px; padding: 15px 10px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        td { padding: 15px 10px; border-bottom: 1px solid rgba(255,255,255,0.05); color: #cbd5e1; font-size: 0.95rem; }
        tr:hover { background: rgba(255,255,255,0.02); }
        
        .tag { padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; letter-spacing: 1px;}
        .tag.y { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .tag.n { background: rgba(255, 75, 89, 0.1); color: #ff4b59; border: 1px solid rgba(255, 75, 89, 0.3); }
        
        /* Magic Link Generator */
        .magic-link-box { background: linear-gradient(135deg, rgba(212,163,115,0.1), transparent); border: 1px solid #d4a373; padding: 30px; border-radius: 15px; margin-bottom: 40px; }
        .magic-flex { display: flex; gap: 15px; margin-top: 15px; }
        .magic-flex input { flex: 1; padding: 15px; border-radius: 8px; border: 1px solid rgba(212,163,115,0.4); background: rgba(0,0,0,0.5); color: white; outline: none; }
        .magic-flex button { padding: 0 30px; background: #d4a373; color: #020408; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sb-head">
            <h2>OPAL PANEL</h2>
            <span><?php echo $_SESSION['role']; ?></span>
        </div>
        <div class="nav-links">
            <div class="nav-btn active" onclick="switchView('v-dash', this)"><i class="fas fa-chart-line"></i> Analytics</div>
            <div class="nav-btn" onclick="switchView('v-guest', this)"><i class="fas fa-clipboard-list"></i> RSVPs & Guests</div>
            
            <!-- Conditional Menus Based on Role -->
            <?php if($_SESSION['role'] === 'SUPER_ADMIN' || $_SESSION['role'] === 'RESELLER'): ?>
            <div class="nav-btn" onclick="switchView('v-clients', this)"><i class="fas fa-building"></i> Manage Clients</div>
            <?php endif; ?>
            
            <div class="nav-btn" onclick="switchView('v-settings', this)"><i class="fas fa-cog"></i> Settings</div>
        </div>
        <div class="sb-foot">
            <a href="?logout=true" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Terminate Session</a>
        </div>
    </div>

    <!-- MAIN VIEW AREA -->
    <div class="main">
        <div class="top-bar">
            <h1>Workspace</h1>
            <div class="user-tag"><i class="far fa-user-circle" style="color:#d4a373; margin-right:8px;"></i> <?php echo $_SESSION['user']; ?></div>
        </div>

        <!-- DASHBOARD VIEW (Analytics & Generator) -->
        <div id="v-dash" class="view-section active">
            
            <div class="stats-grid">
                <div class="stat-card"><div class="s-lbl">Total Forms</div><div class="s-val"><?php echo $stats['total']; ?></div></div>
                <div class="stat-card"><div class="s-lbl">Guests Coming</div><div class="s-val" style="color:#ebd197;"><?php echo $stats['guests']; ?></div></div>
                <div class="stat-card"><div class="s-lbl">Accepted</div><div class="s-val" style="color:#10b981;"><?php echo $stats['attending']; ?></div></div>
                <div class="stat-card"><div class="s-lbl">Declined</div><div class="s-val" style="color:#ff4b59;"><?php echo $stats['declined']; ?></div></div>
            </div>

            <div class="magic-link-box">
                <h3 style="color:#ebd197; margin-bottom:10px;"><i class="fas fa-link"></i> Magic VIP Link Generator</h3>
                <p style="color:#888; font-size:0.9rem;">Type your guest's name to generate a highly personalized entry ticket URL.</p>
                <div class="magic-flex">
                    <input type="text" id="vipNameInput" placeholder="E.g., David & Family">
                    <button onclick="generateLink()">Generate URL</button>
                </div>
                <div id="linkResult" style="margin-top:15px; color:#10b981; font-weight:500; font-size:0.9rem; word-break: break-all;"></div>
            </div>

        </div>

        <!-- GUEST LIST VIEW (RSVP Tables) -->
        <div id="v-guest" class="view-section">
            <div class="table-wrap">
                <div class="t-head">
                    <h2 style="color: #fff; font-weight:400;">Received RSVPs</h2>
                    <input type="text" id="srch" class="search" placeholder="🔍 Search Guests..." onkeyup="searchTable()">
                </div>
                
                <table id="gTable">
                    <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Total</th><th>Notes / Song</th></tr></thead>
                    <tbody>
                        <?php if($stats['total'] > 0): foreach(array_reverse($rsvps) as $row): 
                            $is_yes = ($row['attendance'] ?? '') === "Joyfully Accept";
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['name'] ?? 'N/A'); ?></strong></td>
                            <td style="font-size:0.8rem; color:#888;"><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></td>
                            <td><span class="tag <?php echo $is_yes ? 'y':'n'; ?>"><?php echo $is_yes ? 'Accepted' : 'Declined'; ?></span></td>
                            <td><?php echo htmlspecialchars($row['guests'] ?? '0'); ?></td>
                            <td style="font-size:0.85rem; max-width:250px;"><?php echo htmlspecialchars($row['message'] ?? '-'); ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="5" style="text-align:center; color:#555; padding: 40px;">No forms submitted yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- AGENCY: MANAGE CLIENTS (Only visible to Admin/Reseller) -->
        <div id="v-clients" class="view-section">
            <h2 style="color: #ebd197; margin-bottom: 20px;">Client Portfolio Manager</h2>
            <p style="color: #888; margin-bottom: 40px;">As a Reseller/Agency, you can create and manage separate dashboard access for your couples here.</p>
            
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Couple Identifier</th><th>Username</th><th>Wedding DB</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <tr><td>Emma & James</td><td>emma_james</td><td>wed_default</td><td><span class="tag y">Active</span></td><td><a href="#" style="color:#d4a373;">Edit Link</a></td></tr>
                        <!-- Normally fetched from DB -->
                    </tbody>
                </table>
            </div>
        </div>

        <div id="v-settings" class="view-section">
            <h2 style="color: #ebd197;">System Preferences</h2>
            <p style="color: #888;">Configure site wide variables like timezone, analytics tracking and export functionalities here.</p>
        </div>

    </div>

    <!-- JS LOGIC -->
    <script>
        function switchView(vid, el) {
            document.querySelectorAll('.view-section').forEach(v => v.classList.remove('active'));
            document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(vid).classList.add('active');
            el.classList.add('active');
        }

        function searchTable() {
            let val = document.getElementById('srch').value.toUpperCase();
            let rows = document.getElementById('gTable').getElementsByTagName('tr');
            for(let i=1; i<rows.length; i++) {
                rows[i].style.display = rows[i].innerText.toUpperCase().indexOf(val) > -1 ? "" : "none";
            }
        }

        function generateLink() {
            let nm = document.getElementById('vipNameInput').value;
            if(!nm) return;
            let baseUrl = window.location.href.split('admin.php')[0];
            let fullLink = `${baseUrl}index.html?guest=${encodeURIComponent(nm)}`;
            document.getElementById('linkResult').innerHTML = 
                `Share this securely: <a href="${fullLink}" target="_blank" style="color:#10b981;">${fullLink}</a>`;
        }
    </script>
</body>
</html>