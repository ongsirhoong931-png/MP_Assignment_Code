<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/role_check.php';

allow_roles('admin');

$userId = me()['id'] ?? null;
$admin_id = me()['admin_id'] ?? null;

if (!$admin_id && $userId) {
    $stmt = $pdo->prepare("SELECT admin_id FROM user_accounts WHERE user_id = ?");
    $stmt->execute([$userId]);
    $admin_id = $stmt->fetchColumn();
}

$successMsg = '';
$errorMsg = '';

// Handle Profile Details Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $newUsername = trim($_POST['username'] ?? '');
    $newEmail = trim($_POST['email'] ?? '');
    $newPhone = trim($_POST['phone'] ?? '');

    if (empty($newUsername) || empty($newEmail)) {
        $errorMsg = 'Username and Email cannot be empty.';
    } else {
        try {
            $pdo->beginTransaction();

            $checkStmt = $pdo->prepare("SELECT user_id FROM user_accounts WHERE (username = ? OR email = ?) AND user_id != ?");
            $checkStmt->execute([$newUsername, $newEmail, $userId]);
            if ($checkStmt->fetch()) {
                throw new Exception('Username or Email is already taken by another account.');
            }

            $updateUser = $pdo->prepare("UPDATE user_accounts SET username = ?, email = ? WHERE user_id = ?");
            $updateUser->execute([$newUsername, $newEmail, $userId]);

            $updateAdmin = $pdo->prepare("UPDATE admins SET admin_email = ?, admin_phone_number = ? WHERE admin_id = ?");
            $updateAdmin->execute([$newEmail, $newPhone, $admin_id]);

            $pdo->commit();
            $successMsg = 'Profile updated successfully!';
        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMsg = $e->getMessage();
        }
    }
}

// Handle Image Upload via Camera Badge
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $file = $_FILES['profile_image'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (in_array($file['type'], $allowedTypes)) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = 'admin_' . $admin_id . '_' . time() . '.' . $ext;
            $uploadDir = __DIR__ . '/../uploads/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $destination = $uploadDir . $fileName;
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $imagePath = 'uploads/' . $fileName;
                $updateStmt = $pdo->prepare("UPDATE user_accounts SET profile_image = ? WHERE user_id = ?");
                $updateStmt->execute([$imagePath, $userId]);
                
                header("Location: profile.php?success=1");
                exit;
            } else {
                $errorMsg = 'Failed to move uploaded file.';
            }
        } else {
            $errorMsg = 'Invalid image type. Please upload JPG, PNG, GIF, or WebP.';
        }
    }
}

$stmt = $pdo->prepare("
    SELECT a.*, u.user_id, u.username, u.email as user_email, u.is_active, u.profile_image
    FROM admins a
    LEFT JOIN user_accounts u ON u.admin_id = a.admin_id
    WHERE a.admin_id = ?
");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

$pageTitle = 'Admin Profile';
require __DIR__ . '/../includes/header.php';
?>

<div class="profile-card-portrait">

    <?php if (isset($_GET['success']) || !empty($successMsg)): ?>
        <div class="notice" style="margin-bottom: 20px;"><?= $successMsg ?: 'Profile updated successfully!' ?></div>
    <?php endif; ?>

    <?php if (!empty($errorMsg)): ?>
        <div class="error" style="margin-bottom: 20px;"><?= h($errorMsg) ?></div>
    <?php endif; ?>

    <!-- Profile Picture with Camera Badge -->
    <div class="profile-avatar-container">
        <?php if (!empty($admin['profile_image']) && file_exists(__DIR__ . '/../' . $admin['profile_image'])): ?>
            <img src="<?= h(url($admin['profile_image'])) ?>" alt="Profile Picture" class="profile-avatar-img">
        <?php else: ?>
            <div class="profile-avatar-placeholder">👤</div>
        <?php endif; ?>

        <label for="profile_image" class="profile-upload-btn-badge" title="Change Profile Photo">
            📷
        </label>
    </div>

    <h2><?= h($admin['admin_name'] ?? 'Admin Profile') ?></h2>
    <p style="color: var(--accent); margin-top: -8px; margin-bottom: 20px;">Administrator Account</p>

    <!-- Hidden Photo Upload Form -->
    <form action="" method="POST" enctype="multipart/form-data" id="photoForm">
        <input type="file" name="profile_image" id="profile_image" accept="image/*" style="display: none;" onchange="document.getElementById('photoForm').submit();">
    </form>

    <!-- Static View List -->
    <div id="profileViewMode">
        <ul class="profile-details-list">
            <li><strong>Admin ID:</strong> <span><?= h($admin['admin_id'] ?? 'N/A') ?></span></li>
            <li><strong>Email:</strong> <span><?= h($admin['admin_email'] ?: $admin['user_email']) ?></span></li>
            <li><strong>Phone:</strong> <span><?= h($admin['admin_phone_number'] ?? 'N/A') ?></span></li>
            <li><strong>Username:</strong> <code><?= h($admin['username'] ?? me()['username']) ?></code></li>
            <li><strong>Status:</strong> <span style="color: #6EE7B7;"><?= ($admin['is_active'] ?? 1) ? 'Active' : 'Inactive' ?></span></li>
        </ul>

        <div style="display: flex; gap: 10px; margin-top: 24px;">
            <button type="button" class="button button-outline" onclick="toggleEditMode()" style="flex: 1;">Edit Profile</button>
            <a class="button" href="<?= h(url('admin/dashboard.php')) ?>" style="flex: 1; text-align: center;">Dashboard</a>
        </div>
    </div>

    <!-- Editable Form (Hidden by default) -->
    <form action="" method="POST" id="profileEditMode" style="display: none; text-align: left; background: var(--surface2); padding: 18px; border-radius: 12px; border: 1px solid var(--line); margin-top: 10px;">
        <input type="hidden" name="update_profile" value="1">
        
        <div style="margin-bottom: 12px;">
            <label style="font-size: 13px; margin-bottom: 4px; display: block;">Username:</label>
            <input type="text" name="username" value="<?= h($admin['username']) ?>" required style="margin-top: 0; padding: 8px 12px; font-size: 14px;">
        </div>

        <div style="margin-bottom: 12px;">
            <label style="font-size: 13px; margin-bottom: 4px; display: block;">Email Address:</label>
            <input type="email" name="email" value="<?= h($admin['admin_email'] ?: $admin['user_email']) ?>" required style="margin-top: 0; padding: 8px 12px; font-size: 14px;">
        </div>

        <div style="margin-bottom: 16px;">
            <label style="font-size: 13px; margin-bottom: 4px; display: block;">Phone Number:</label>
            <input type="text" name="phone" value="<?= h($admin['admin_phone_number']) ?>" style="margin-top: 0; padding: 8px 12px; font-size: 14px;">
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="button" style="flex: 1; padding: 8px; font-size: 14px; text-align: center;">Save</button>
            <button type="button" class="button button-outline" onclick="toggleEditMode()" style="flex: 1; padding: 8px; font-size: 14px; text-align: center;">Cancel</button>
        </div>
    </form>

</div>

<script>
function toggleEditMode() {
    var viewMode = document.getElementById('profileViewMode');
    var editMode = document.getElementById('profileEditMode');
    if (viewMode.style.display === 'none') {
        viewMode.style.display = 'block';
        editMode.style.display = 'none';
    } else {
        viewMode.style.display = 'none';
        editMode.style.display = 'block';
    }
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>