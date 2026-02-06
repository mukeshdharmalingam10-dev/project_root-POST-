<?php
session_start();
include "db.php";

// Redirect if not logged in
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch logged-in user info (with profile image)
// Fetch logged-in user info (with profile image)
$stmt = $conn->prepare("SELECT full_name, email, mobile, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Determine profile image path
$profileSrc = '';
if (!empty($user['profile_pic'])) {
    $profileSrc = $user['profile_pic'];
} else {
    $profileSrc = "uploads/profile/default.png";
}

// Handle Add/Edit Project Submission
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_action'])){
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $techs = $_POST['technologies'] ?? [];
    $project_id = $_POST['project_id'] ?? null;

    if($project_id){ // Update
        $stmt = $conn->prepare("UPDATE projects SET title=?, description=? WHERE id=? AND user_id=?");
        $stmt->bind_param("ssii",$title,$description,$project_id,$user_id);
        $stmt->execute();

        // Clear old technologies
        $stmtDelTech = $conn->prepare("DELETE FROM project_technologies WHERE project_id=?");
        $stmtDelTech->bind_param("i", $project_id);
        $stmtDelTech->execute();
        $stmtDelTech->close();

        // Insert new technologies
        $stmt_tech = $conn->prepare("INSERT INTO project_technologies (project_id, tech_id) VALUES (?,?)");
        foreach($techs as $tech){
            $stmt_tech->bind_param("ii",$project_id,$tech);
            $stmt_tech->execute();
        }

    } else { // Add New
        $stmt = $conn->prepare("INSERT INTO projects (user_id,title,description) VALUES (?,?,?)");
        $stmt->bind_param("iss",$user_id,$title,$description);
        $stmt->execute();
        $project_id = $stmt->insert_id;

        // Insert technologies
        $stmt_tech = $conn->prepare("INSERT INTO project_technologies (project_id, tech_id) VALUES (?,?)");
        foreach($techs as $tech){
            $stmt_tech->bind_param("ii",$project_id,$tech);
            $stmt_tech->execute();
        }
    }

    // Handle removal of existing files (edit mode)
    if($project_id && !empty($_POST['remove_files']) && is_array($_POST['remove_files'])){
        foreach($_POST['remove_files'] as $fileId){
            $fileId = (int)$fileId;
            if($fileId <= 0) continue;

            // Get file path for deletion from disk
            $stmt_get = $conn->prepare("SELECT file_path FROM project_files WHERE id=? AND project_id=?");
            $stmt_get->bind_param("ii", $fileId, $project_id);
            $stmt_get->execute();
            $res_get = $stmt_get->get_result();
            if($res_get && $row = $res_get->fetch_assoc()){
                $path = $row['file_path'];
                if($path && file_exists($path)){
                    @unlink($path);
                }
            }

            // Remove DB record
            $stmt_del = $conn->prepare("DELETE FROM project_files WHERE id=? AND project_id=?");
            $stmt_del->bind_param("ii", $fileId, $project_id);
            $stmt_del->execute();
        }
    }

    // Handle file uploads
    if(isset($_FILES['project_files'])){
        foreach($_FILES['project_files']['name'] as $key => $name){
            if($_FILES['project_files']['error'][$key] === 0){
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $file_path = "uploads/projects/" . uniqid() . "." . $ext;
                move_uploaded_file($_FILES['project_files']['tmp_name'][$key], $file_path);

                $stmt_file = $conn->prepare("INSERT INTO project_files (project_id, file_name, file_path) VALUES (?,?,?)");
                $stmt_file->bind_param("iss",$project_id,$name,$file_path);
                $stmt_file->execute();
            }
        }
    }

    header("Location: dashboard.php");
    exit;
}

// Handle Delete Project
if(isset($_GET['delete'])){
    $delete_id = (int)$_GET['delete'];
    $stmtDel = $conn->prepare("DELETE FROM projects WHERE id=? AND user_id=?");
    $stmtDel->bind_param("ii", $delete_id, $user_id);
    $stmtDel->execute();
    $stmtDel->close();
    header("Location: dashboard.php");
    exit;
}

// Pagination setup
$limit = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Fetch projects
$stmt = $conn->prepare("SELECT * FROM projects WHERE user_id=? ORDER BY created_at DESC LIMIT ?,?");
$stmt->bind_param("iii",$user_id,$start,$limit);
$stmt->execute();
$projects = $stmt->get_result();
$stmt->close();

// Count total projects
$stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM projects WHERE user_id=?");
$stmtCount->bind_param("i", $user_id);
$stmtCount->execute();
$resCount = $stmtCount->get_result()->fetch_assoc();
$total_projects = $resCount ? (int)$resCount['total'] : 0;
$stmtCount->close();
$total_pages = ceil($total_projects / $limit);

// Fetch all technologies
$technologies = [];
$stmtTechList = $conn->prepare("SELECT id, tech_name FROM technologies");
$stmtTechList->execute();
$techs_res = $stmtTechList->get_result();
while($row = $techs_res->fetch_assoc()){
    $technologies[] = $row;
}
$stmtTechList->close();

// Edit mode: fetch project + related data for pre-fill
$edit_project = null;
$edit_tech_ids = [];
$edit_files = [];
if(isset($_GET['edit'])){
    $edit_id = (int)$_GET['edit'];

    // Project (ensure it belongs to the logged-in user)
    $stmt_edit = $conn->prepare("SELECT * FROM projects WHERE id=? AND user_id=?");
    $stmt_edit->bind_param("ii", $edit_id, $user_id);
    $stmt_edit->execute();
    $res_edit = $stmt_edit->get_result();
    if($res_edit && $res_edit->num_rows === 1){
        $edit_project = $res_edit->fetch_assoc();

        // Technologies for this project
        $stmtTechIds = $conn->prepare("SELECT tech_id FROM project_technologies WHERE project_id=?");
        $stmtTechIds->bind_param("i", $edit_project['id']);
        $stmtTechIds->execute();
        $tech_res = $stmtTechIds->get_result();
        while($t = $tech_res->fetch_assoc()){
            $edit_tech_ids[] = (int)$t['tech_id'];
        }
        $stmtTechIds->close();

        // Files for this project
        $stmtFiles = $conn->prepare("SELECT id, file_name, file_path FROM project_files WHERE project_id=?");
        $stmtFiles->bind_param("i", $edit_project['id']);
        $stmtFiles->execute();
        $file_res = $stmtFiles->get_result();
        while($f = $file_res->fetch_assoc()){
            $edit_files[] = $f;
        }
        $stmtFiles->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard</title>
<link rel="stylesheet" href="assets/css/dashboard.css">
<script src="assets/js/dashboard.js" defer></script>
</head>
<body>
<div class="dashboard-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Dashboard</h2>
        <ul>
            <li><a href="#home">Home</a></li>
            <li><a href="#projects">Projects</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <!-- Profile Wrapper -->
                <div class="profile-wrapper" onclick="toggleProfileDropdown()">
                    <div class="user-info">
                        <?= $profileSrc ? "<img src='{$profileSrc}' alt='Profile'>" : "" ?>
                        <span><?= htmlspecialchars($user['full_name']) ?></span>
                        <span class="dropdown-arrow">▼</span>
                    </div>
                    
                    <!-- Dropdown -->
                    <div class="profile-dropdown" id="profileDropdown">
                        <div class="dropdown-header">
                            <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                            <span class="email"><?= htmlspecialchars($user['email']) ?></span>
                            <?php if(!empty($user['mobile'])): ?>
                                <span class="mobile"><?= htmlspecialchars($user['mobile']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item logout">
                            <span class="icon">⏻</span> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scrollable Content Area -->
        <div class="content-scrollable">
            <!-- Projects Section -->
            <section id="projects">
            <div class="projects-header">
                <h3>Manage Projects</h3>
                <button onclick="showProjectForm()">Add New Project</button>
            </div>

            <!-- Add/Edit Form -->
            <div id="projectFormContainer" class="form-card" style="<?= $edit_project ? '' : 'display:none;' ?>">
                <form method="POST" enctype="multipart/form-data" id="projectForm">
                    <input type="hidden" name="project_id" id="project_id" value="<?= $edit_project ? (int)$edit_project['id'] : '' ?>">
                    <input type="hidden" name="project_action" value="save">
                    <div class="form-layout">
                        <div class="form-group">
                            <label>Title</label>
                            <input
                                type="text"
                                name="title"
                                id="title"
                                required
                                value="<?= $edit_project ? htmlspecialchars($edit_project['title']) : '' ?>"
                            >
                            <span class="error-msg" id="titleError"></span>
                        </div>

                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" id="description" required><?= $edit_project ? htmlspecialchars($edit_project['description']) : '' ?></textarea>
                            <span class="error-msg" id="descriptionError"></span>
                        </div>

                        <div class="form-group">
                            <label>Technologies</label>
                            <select name="technologies[]" id="technologies" multiple required>
                                <?php foreach($technologies as $tech):
                                    $selected = ($edit_project && in_array((int)$tech['id'], $edit_tech_ids)) ? 'selected' : '';
                                ?>
                                    <option value="<?= $tech['id'] ?>" <?= $selected ?>>
                                        <?= htmlspecialchars($tech['tech_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="error-msg" id="technologiesError"></span>
                        </div>

                        <div class="form-group">
                            <label>Project Files</label>
                            <input type="file" name="project_files[]" id="project_files" multiple>
                            <div id="filePreview" class="file-preview"></div>
                            <span class="error-msg" id="projectFilesError"></span>

                            <div id="removeFilesContainer"></div>

                            <?php if($edit_project && !empty($edit_files)): ?>
                                <div class="existing-files" style="display: flex; flex-wrap: wrap; gap: 12px; margin-top: 8px;">
                                    <?php foreach($edit_files as $file): 
                                        $ext = strtolower(pathinfo($file['file_name'], PATHINFO_EXTENSION));
                                        $is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                        
                                        $icon = '📄'; $color = '#6b7280';
                                        if($ext === 'pdf') { $icon = '📄'; $color = '#ef4444'; }
                                        elseif(in_array($ext, ['xls','xlsx','csv'])) { $icon = '📊'; $color = '#10b981'; }
                                        elseif(in_array($ext, ['doc','docx'])) { $icon = '📝'; $color = '#3b82f6'; }
                                        elseif(in_array($ext, ['zip','rar'])) { $icon = '📦'; $color = '#f59e0b'; }
                                    ?>
                                        <div class="existing-file-item" data-file-id="<?= (int)$file['id'] ?>">
                                            <a href="<?= $file['file_path'] ?>" target="_blank" title="View File">
                                                <?php if($is_image): ?>
                                                    <img src="<?= $file['file_path'] ?>" alt="<?= htmlspecialchars($file['file_name']) ?>">
                                                <?php else: ?>
                                                    <div class="file-icon" style="color: <?= $color ?>; border:none; width:100%; height:100%; margin:0;"><?= $icon ?></div>
                                                <?php endif; ?>
                                            </a>
                                            <span title="<?= htmlspecialchars($file['file_name']) ?>">
                                                <?= htmlspecialchars($file['file_name']) ?>
                                            </span>
                                            <button type="button" class="remove-existing-file" data-file-id="<?= (int)$file['id'] ?>">
                                                &times;
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit">Save Project</button>
                        <button type="button" class="secondary" onclick="hideProjectForm()">Cancel</button>
                    </div>
                </form>
            </div>

            <!-- Projects List -->
            <div class="project-list">
                <?php if($projects->num_rows > 0): ?>
                    <table>
                        <tr>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Technologies</th>
                            <th>Files</th>
                            <th>Actions</th>
                        </tr>
                        <?php while($proj = $projects->fetch_assoc()):
                            $tech_ids = [];
                            $stmtProjTech = $conn->prepare("SELECT t.tech_name FROM project_technologies pt JOIN technologies t ON pt.tech_id=t.id WHERE pt.project_id=?");
                            $stmtProjTech->bind_param("i", $proj['id']);
                            $stmtProjTech->execute();
                            $tech_res = $stmtProjTech->get_result();
                            while($t = $tech_res->fetch_assoc()) $tech_ids[] = $t['tech_name'];
                            $stmtProjTech->close();

                            $files = [];
                            $stmtProjFiles = $conn->prepare("SELECT file_name, file_path FROM project_files WHERE project_id=?");
                            $stmtProjFiles->bind_param("i", $proj['id']);
                            $stmtProjFiles->execute();
                            $file_res = $stmtProjFiles->get_result();
                            while($f = $file_res->fetch_assoc()) $files[] = $f;
                            $stmtProjFiles->close();
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($proj['title']) ?></td>
                            <td><?= htmlspecialchars($proj['description']) ?></td>
                            <td><?= implode(", ", $tech_ids) ?></td>
                            <td>
                                <?php foreach($files as $f): ?>
                                    <a href="<?= $f['file_path'] ?>" target="_blank"><?= htmlspecialchars($f['file_name']) ?></a><br>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <button onclick="window.location.href='dashboard.php?edit=<?= $proj['id'] ?>'">Edit</button>

                                <!-- Inline Delete -->
                                <button class="delete-btn" data-id="<?= $proj['id'] ?>">Delete</button>
                                <div class="delete-confirm" id="delete-confirm-<?= $proj['id'] ?>" style="display:none;">
                                    <span>Are you sure?</span>
                                    <button onclick="confirmDeleteJS(<?= $proj['id'] ?>)">Yes</button>
                                    <button onclick="cancelDelete(<?= $proj['id'] ?>)">No</button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </table>

                    <div class="pagination">
                        <?php for($i=1;$i<=$total_pages;$i++): ?>
                            <a href="?page=<?= $i ?>" <?= $i==$page?'class=active':'' ?>><?= $i ?></a>
                        <?php endfor; ?>
                    </div>
                <?php else: ?>
                    <p>No data exists</p>
                <?php endif; ?>
            </div>
        </section>
        </div><!-- End main -->
</div>
</body>
</html>
