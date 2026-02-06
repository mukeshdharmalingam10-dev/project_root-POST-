<?php
session_start();
include "db.php";

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'save_project':
        save_project($conn, $user_id);
        break;
    case 'delete_project':
        delete_project($conn, $user_id);
        break;
    case 'get_project':
        get_project($conn, $user_id);
        break;
    case 'fetch_projects':
        fetch_projects($conn, $user_id);
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
        exit;
}

function save_project($conn, $user_id) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $techs = $_POST['technologies'] ?? [];
    $project_id = $_POST['project_id'] ?? null;
    
    // Convert empty string project_id to null
    if(empty($project_id)) $project_id = null;

    if (empty($title) || empty($description)) {
        echo json_encode(['status' => 'error', 'message' => 'Title and Description are required.']);
        exit;
    }

    if ($project_id) {
        // Update
        $stmt = $conn->prepare("UPDATE projects SET title=?, description=? WHERE id=? AND user_id=?");
        $stmt->bind_param("ssii", $title, $description, $project_id, $user_id);
        if ($stmt->execute()) {
            // Update technologies
            $stmtDelTech = $conn->prepare("DELETE FROM project_technologies WHERE project_id=?");
            $stmtDelTech->bind_param("i", $project_id);
            $stmtDelTech->execute();
            $stmtDelTech->close();

            $stmt_tech = $conn->prepare("INSERT INTO project_technologies (project_id, tech_id) VALUES (?,?)");
            foreach ($techs as $tech) {
                $stmt_tech->bind_param("ii", $project_id, $tech);
                $stmt_tech->execute();
            }
            $stmt_tech->close();

            // Handle file removal
            if (!empty($_POST['remove_files']) && is_array($_POST['remove_files'])) {
                foreach ($_POST['remove_files'] as $fileId) {
                    $fileId = (int)$fileId;
                    if ($fileId <= 0) continue;

                    $stmt_get = $conn->prepare("SELECT file_path FROM project_files WHERE id=? AND project_id=?");
                    $stmt_get->bind_param("ii", $fileId, $project_id);
                    $stmt_get->execute();
                    $res_get = $stmt_get->get_result();
                    if ($res_get && $row = $res_get->fetch_assoc()) {
                        $path = $row['file_path'];
                        if ($path && file_exists($path)) {
                            @unlink($path);
                        }
                    }
                    $stmt_del = $conn->prepare("DELETE FROM project_files WHERE id=? AND project_id=?");
                    $stmt_del->bind_param("ii", $fileId, $project_id);
                    $stmt_del->execute();
                }
            }

            // Handle file uploads
            handle_file_uploads($conn, $project_id);

            echo json_encode(['status' => 'success', 'message' => 'Project updated successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update project.']);
        }
        $stmt->close();
    } else {
        // Add New
        $stmt = $conn->prepare("INSERT INTO projects (user_id, title, description) VALUES (?,?,?)");
        $stmt->bind_param("iss", $user_id, $title, $description);
        if ($stmt->execute()) {
            $project_id = $stmt->insert_id;

            $stmt_tech = $conn->prepare("INSERT INTO project_technologies (project_id, tech_id) VALUES (?,?)");
            foreach ($techs as $tech) {
                $stmt_tech->bind_param("ii", $project_id, $tech);
                $stmt_tech->execute();
            }
            $stmt_tech->close();

            handle_file_uploads($conn, $project_id);

            echo json_encode(['status' => 'success', 'message' => 'Project created successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create project.']);
        }
        $stmt->close();
    }
}

function handle_file_uploads($conn, $project_id) {
    if (isset($_FILES['project_files'])) {
        foreach ($_FILES['project_files']['name'] as $key => $name) {
            if ($_FILES['project_files']['error'][$key] === 0) {
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                if (!is_dir('uploads/projects')) {
                    mkdir('uploads/projects', 0777, true);
                }
                $file_path = "uploads/projects/" . uniqid() . "." . $ext;
                if (move_uploaded_file($_FILES['project_files']['tmp_name'][$key], $file_path)) {
                    $stmt_file = $conn->prepare("INSERT INTO project_files (project_id, file_name, file_path) VALUES (?,?,?)");
                    $stmt_file->bind_param("iss", $project_id, $name, $file_path);
                    $stmt_file->execute();
                    $stmt_file->close();
                }
            }
        }
    }
}

function delete_project($conn, $user_id) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
        exit;
    }

    // Delete files from disk first
    $stmtFiles = $conn->prepare("SELECT file_path FROM project_files WHERE project_id=? AND project_id IN (SELECT id FROM projects WHERE user_id=?)");
    $stmtFiles->bind_param("ii", $id, $user_id);
    $stmtFiles->execute();
    $resFiles = $stmtFiles->get_result();
    while ($f = $resFiles->fetch_assoc()) {
        if (file_exists($f['file_path'])) {
            @unlink($f['file_path']);
        }
    }
    $stmtFiles->close();

    $stmt = $conn->prepare("DELETE FROM projects WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Project deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete project.']);
    }
    $stmt->close();
}

function get_project($conn, $user_id) {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid ID.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM projects WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        // Fetch Techs include id and name
        $techs = [];
        $stmtTech = $conn->prepare("SELECT t.id, t.tech_name FROM project_technologies pt JOIN technologies t ON pt.tech_id=t.id WHERE pt.project_id=?");
        $stmtTech->bind_param("i", $id);
        $stmtTech->execute();
        $resTech = $stmtTech->get_result();
        while ($t = $resTech->fetch_assoc()) {
            $techs[] = $t;
        }

        // Fetch Files
        $files = [];
        $stmtFile = $conn->prepare("SELECT id, file_name, file_path FROM project_files WHERE project_id=?");
        $stmtFile->bind_param("i", $id);
        $stmtFile->execute();
        $resFile = $stmtFile->get_result();
        while ($f = $resFile->fetch_assoc()) {
            $files[] = $f;
        }

        $row['technologies'] = $techs;
        $row['files'] = $files;

        echo json_encode(['status' => 'success', 'data' => $row]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Project not found.']);
    }
    $stmt->close();
}

function fetch_projects($conn, $user_id) {
    // Basic pagination (optional)
    // For now returning all or limit 50; preserving the original query logic if needed
    // The user had pagination limit 5. We can stick to that or just fetch updated rows.
    // Let's implement dynamic fetching for the table body.
    
    // We can try to respect the page parameter if passed, otherwise default to page 1
    $limit = 5;
    $page = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 1;
    $start = ($page - 1) * $limit;

    $stmt = $conn->prepare("SELECT * FROM projects WHERE user_id=? ORDER BY created_at DESC LIMIT ?,?");
    $stmt->bind_param("iii", $user_id, $start, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $projects = [];
    while ($row = $result->fetch_assoc()) {
         // Technologies
         $tech_ids = [];
         $stmtProjTech = $conn->prepare("SELECT t.tech_name FROM project_technologies pt JOIN technologies t ON pt.tech_id=t.id WHERE pt.project_id=?");
         $stmtProjTech->bind_param("i", $row['id']);
         $stmtProjTech->execute();
         $tech_res = $stmtProjTech->get_result();
         while($t = $tech_res->fetch_assoc()) $tech_ids[] = $t['tech_name'];
         $row['tech_names'] = implode(", ", $tech_ids);
         
         // Files
         $files = [];
         $stmtProjFiles = $conn->prepare("SELECT file_name, file_path FROM project_files WHERE project_id=?");
         $stmtProjFiles->bind_param("i", $row['id']);
         $stmtProjFiles->execute();
         $file_res = $stmtProjFiles->get_result();
         while($f = $file_res->fetch_assoc()) $files[] = $f;
         $row['files'] = $files;

         $projects[] = $row;
    }

    // Count total for pagination
    $stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM projects WHERE user_id=?");
    $stmtCount->bind_param("i", $user_id);
    $stmtCount->execute();
    $resCount = $stmtCount->get_result()->fetch_assoc();
    $total_projects = $resCount ? (int)$resCount['total'] : 0;
    $total_pages = ceil($total_projects / $limit);

    echo json_encode([
        'status' => 'success',
        'data' => $projects,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages
        ]
    ]);
}
?>
