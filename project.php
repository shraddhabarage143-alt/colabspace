<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$db = "colabspace";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]);
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'fetch_project_data':
            fetchProjectData($conn, $_POST['project_id'] ?? null);
            break;
        case 'get_project_info':
            getProjectInfo($conn, $_POST['project_id'] ?? null);
            break;
        case 'add_member':
            addMember($conn, $_POST);
            break;
        case 'update_member':
            updateMember($conn, $_POST);
            break;
        case 'delete_member':
            deleteMember($conn, $_POST['joined_id'] ?? null);
            break;
        case 'add_idea':
            addIdea($conn, $_POST);
            break;
        case 'update_idea':
            updateIdea($conn, $_POST);
            break;
        case 'delete_idea':
            deleteIdea($conn, $_POST['uid'] ?? null);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
    exit;
}

function fetchProjectData($conn, $project_id) {
    if (!$project_id) {
        echo json_encode(['success' => false, 'message' => 'Project ID is required']);
        exit;
    }

    // Fetch team members
    $membersQuery = "SELECT joined_id, name, username, email FROM project_joined WHERE project_id = ?";
    $stmt = $conn->prepare($membersQuery);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("s", $project_id);
    $stmt->execute();
    $membersResult = $stmt->get_result();
    $members = $membersResult->fetch_all(MYSQLI_ASSOC);

    // Fetch ideas
    $ideasQuery = "SELECT * FROM project_ideas WHERE project_id = ? ORDER BY created_on DESC";
    $stmt = $conn->prepare($ideasQuery);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("s", $project_id);
    $stmt->execute();
    $ideasResult = $stmt->get_result();
    $ideas = $ideasResult->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'members' => $members,
        'ideas' => $ideas
    ]);
    exit;
}

function getProjectInfo($conn, $project_id) {
    if (!$project_id) {
        echo json_encode(['success' => false, 'message' => 'Project ID is required']);
        exit;
    }

    $query = "SELECT * FROM projects WHERE uid = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("s", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $project = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'project' => $project
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Project not found'
        ]);
    }
    exit;
}

function addMember($conn, $data) {
    $joined_id = $data['joined_id'] ?? null;
    $project_id = $data['project_id'] ?? null;
    $name = $data['name'] ?? null;
    $role = $data['role'] ?? null;
    $status = $data['status'] ?? null;

    if (!$joined_id || !$project_id || !$name || !$role || !$status) {
        echo json_encode([
            'success' => false,
            'message' => 'All fields (joined_id, project_id, name, role, status) are required'
        ]);
        exit;
    }

    // Check if joined_id already exists
    $checkQuery = "SELECT joined_id FROM project_joined WHERE joined_id = ?";
    $stmtCheck = $conn->prepare($checkQuery);
    if (!$stmtCheck) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
        exit;
    }
    $stmtCheck->bind_param("s", $joined_id);
    $stmtCheck->execute();
    $stmtCheck->store_result();
    if ($stmtCheck->num_rows > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Joined ID already exists. Please use a unique ID.'
        ]);
        exit;
    }

    $query = "INSERT INTO project_joined (joined_id, name, project_id, role, status) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("sssss", $joined_id, $name, $project_id, $role, $status);

    if ($stmt->execute()) {
        $member = [
            'joined_id' => $joined_id,
            'name' => $name,
            'project_id' => $project_id,
            'role' => $role,
            'status' => $status
        ];

        echo json_encode([
            'success' => true,
            'message' => 'Team member added successfully!',
            'member' => $member
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to add team member: ' . $stmt->error
        ]);
    }
    exit;
}

function updateMember($conn, $data) {
    $joined_id = $data['joined_id'] ?? null;
    $name = $data['name'] ?? null;
    $role = $data['role'] ?? null;
    $status = $data['status'] ?? null;

    if (!$joined_id || !$name || !$role || !$status) {
        echo json_encode([
            'success' => false,
            'message' => 'All fields (joined_id, name, role, status) are required'
        ]);
        exit;
    }

    $query = "UPDATE project_joined SET name = ?, role = ?, status = ? WHERE joined_id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("ssss", $name, $role, $status, $joined_id);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Team member updated successfully!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update team member: ' . $stmt->error
        ]);
    }
    exit;
}

function deleteMember($conn, $joined_id) {
    if (!$joined_id) {
        echo json_encode(['success' => false, 'message' => 'Joined ID is required']);
        exit;
    }

    $query = "DELETE FROM project_joined WHERE joined_id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("s", $joined_id);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Team member removed successfully!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to remove team member: ' . $stmt->error
        ]);
    }
    exit;
}

function addIdea($conn, $data) {
    $project_id = $data['project_id'] ?? null;
    $creator_id = $data['creator_id'] ?? null;
    $idea = $data['idea'] ?? null;

    if (!$project_id || !$creator_id || !$idea) {
        echo json_encode(['success' => false, 'message' => 'All fields (project_id, creator_id, idea) are required']);
        exit;
    }

    $uid = 'I' . time() . rand(1000, 9999);
    $created_on = date('Y-m-d H:i:s');

    $query = "INSERT INTO project_ideas (uid, project_id, creator_id, idea, created_on) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("sssss", $uid, $project_id, $creator_id, $idea, $created_on);

    if ($stmt->execute()) {
        $ideaData = [
            'uid' => $uid,
            'project_id' => $project_id,
            'creator_id' => $creator_id,
            'idea' => $idea,
            'created_on' => $created_on
        ];

        echo json_encode([
            'success' => true,
            'message' => 'Idea shared successfully!',
            'idea' => $ideaData
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to share idea: ' . $stmt->error
        ]);
    }
    exit;
}

function updateIdea($conn, $data) {
    $uid = $data['uid'] ?? null;
    $idea = $data['idea'] ?? null;

    if (!$uid || !$idea) {
        echo json_encode(['success' => false, 'message' => 'UID and idea are required']);
        exit;
    }

    $query = "UPDATE project_ideas SET idea = ? WHERE uid = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("ss", $idea, $uid);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Idea updated successfully!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update idea: ' . $stmt->error
        ]);
    }
    exit;
}

function deleteIdea($conn, $uid) {
    if (!$uid) {
        echo json_encode(['success' => false, 'message' => 'UID is required']);
        exit;
    }

    $query = "DELETE FROM project_ideas WHERE uid = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("s", $uid);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Idea deleted successfully!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete idea: ' . $stmt->error
        ]);
    }
    exit;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CollabSpace Pro - Project Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        .navbar {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95) !important;
        }
        
        /* Fixed height containers */
        .fixed-height-card {
            height: 600px;
            display: flex;
            flex-direction: column;
        }
        
        .scrollable-content {
            flex: 1;
            overflow-y: auto;
            min-height: 0;
        }
        
        .card-body.scrollable-content {
            max-height: none;
        }
        
        /* Empty state styling */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #6c757d;
            text-align: center;
            padding: 2rem;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .empty-state p {
            margin: 0;
            font-size: 0.9rem;
        }

        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .member-actions, .idea-actions {
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .list-group-item:hover .member-actions,
        .card:hover .idea-actions {
            opacity: 1;
        }

        .edit-mode {
            background-color: #fff3cd !important;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold text-primary" href="entry.php">
                <i class="fas fa-arrow-left me-2"></i>CollabSpace Pro
            </a>
            <div class="d-flex align-items-center">
                <span class="badge bg-primary me-2" id="projectTitle">Loading...</span>
                <span class="badge bg-success me-2"><i class="fas fa-users"></i> <span id="totalMembers">0</span> Members</span>
                <span class="badge bg-warning me-3"><i class="fas fa-lightbulb"></i> <span id="totalIdeas">0</span> Ideas</span>
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> John Doe
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#"><i class="fas fa-cog"></i> Settings</a></li>
                        <li><a class="dropdown-item" href="entry.php"><i class="fas fa-home"></i> Dashboard</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid py-4">
        <div class="row g-3">
            <!-- Team Section -->
            <div class="col-lg-4">
                <div class="card fixed-height-card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-users text-success"></i> Team Members</h6>
                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                            <i class="fas fa-user-plus"></i>
                        </button>
                    </div>
                    <div class="scrollable-content p-0">
                        <div class="list-group list-group-flush" id="teamList">
                            <div class="empty-state">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ideas Section -->
            <div class="col-lg-8">
                <div class="card fixed-height-card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-brain text-warning"></i> Innovation Hub</h6>
                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#newIdeaModal">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="card-body scrollable-content" id="ideasList">
                        <div class="empty-state">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- AI Output -->
                    <div class="alert alert-info m-3 d-none" id="aiOutput">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0"><i class="fas fa-robot"></i> AI Analysis</h6>
                            <button class="btn-close" onclick="closeAiOutput()"></button>
                        </div>
                        <div id="aiOutputContent"></div>
                        <button class="btn btn-sm btn-primary mt-2" onclick="downloadPDF()">
                            <i class="fas fa-download"></i> Export PDF
                        </button>
                    </div>

                    <div class="card-footer bg-light">
                        <div class="d-flex justify-content-between">
                            <div class="btn-group" role="group">
                                <button class="btn btn-outline-secondary btn-sm" onclick="toggleEditMode()">
                                    <i class="fas fa-edit"></i> <span id="editModeText">Edit</span>
                                </button>
                                <button class="btn btn-outline-danger btn-sm" onclick="deleteSelectedIdeas()" id="deleteBtn" disabled>
                                    <i class="fas fa-trash"></i> Delete Selected
                                </button>
                            </div>
                            <div class="btn-group" role="group">
                                <button class="btn btn-info btn-sm" onclick="aiAnalyze('summarize')">Summarize</button>
                                <button class="btn btn-info btn-sm" onclick="aiAnalyze('analyze')">Analyze</button>
                                <button class="btn btn-info btn-sm" onclick="aiAnalyze('expand')">Expand</button>
                                <button class="btn btn-info btn-sm" onclick="aiAnalyze('gaps')">Find Gaps</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- Add Member Modal -->
<div class="modal fade" id="addMemberModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Team Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form onsubmit="addMember(event)">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="memberName" required>
                    </div>
                    <!-- <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="memberName" required>
                    </div> -->
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" id="memberRole">
                            <option value="member">Team Member</option>
                            <option value="lead">Project Lead</option>
                            <option value="admin">Administrator</option>
                            <option value="designer">Designer</option>
                            <option value="developer">Developer</option>
                            <option value="analyst">Analyst</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="memberStatus">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <span class="spinner-border spinner-border-sm d-none me-2"></span>
                        Add Member
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

    <!-- Edit Member Modal -->
    <div class="modal fade" id="editMemberModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Team Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form onsubmit="updateMember(event)">
                    <div class="modal-body">
                        <input type="hidden" id="editMemberId">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="editMemberName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" id="editMemberRole">
                                <option value="member">Team Member</option>
                                <option value="lead">Project Lead</option>
                                <option value="admin">Administrator</option>
                                <option value="designer">Designer</option>
                                <option value="developer">Developer</option>
                                <option value="analyst">Analyst</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="editMemberStatus">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="spinner-border spinner-border-sm d-none me-2"></span>
                            Update Member
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- New Idea Modal -->
    <div class="modal fade" id="newIdeaModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Share New Idea</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form onsubmit="addIdea(event)">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Idea Description</label>
                            <textarea class="form-control" id="ideaContent" rows="6" required 
                                placeholder="Describe your innovative idea in detail..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <span class="spinner-border spinner-border-sm d-none me-2"></span>
                            Share Idea
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Idea Modal -->
    <div class="modal fade" id="editIdeaModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Idea</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form onsubmit="updateIdea(event)">
                    <div class="modal-body">
                        <input type="hidden" id="editIdeaId">
                        <div class="mb-3">
                            <label class="form-label">Idea Description</label>
                            <textarea class="form-control" id="editIdeaContent" rows="6" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="spinner-border spinner-border-sm d-none me-2"></span>
                            Update Idea
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let projectId = '';
        let currentUserId = 'U123456789'; // This should come from session/auth
        let teamMembers = [];
        let ideas = [];
        let editMode = false;
        let selectedIdeas = new Set();

        // Get project ID from URL
        function getProjectIdFromURL() {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('project_id') || 'P17263584761234'; // Default for testing
        }

       document.addEventListener('DOMContentLoaded', function() {
            projectId = getProjectIdFromURL();
            if (!projectId) {
                alert('No project ID provided!');
                return;
            }
            
            loadProjectData();
            loadProjectInfo();
        });

        function generateJoinedId() {
            return 'J' + Date.now() + Math.floor(1000 + Math.random() * 9000);
        }

        // API Functions
        async function apiCall(action, data = {}) {
            try {
                const formData = new FormData();
                formData.append('action', action);
                
                Object.keys(data).forEach(key => {
                    formData.append(key, data[key]);
                });

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                return await response.json();
            } catch (error) {
                console.error('API Error:', error);
                return { success: false, message: 'Network error occurred' };
            }
        }

        // Load project data
        async function loadProjectData() {
    try {
        const formData = new FormData();
        formData.append('action', 'fetch_project_data');
        formData.append('project_id', projectId);

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        const text = await response.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch (parseError) {
            console.error('Response text:', text);
            throw new Error('Invalid JSON response from server');
        }
        
        if (result.success) {
            teamMembers = result.members;
            ideas = result.ideas;
            render();
        } else {
            showAlert('Error loading project data: ' + result.message, 'danger');
        }
    } catch (error) {
        console.error('Error loading project data:', error);
        showAlert('Error loading project data: ' + error.message, 'danger');
    }
}

        // Load project info
        async function loadProjectInfo() {
            const result = await apiCall('get_project_info', { project_id: projectId });
            
            if (result.success) {
                document.getElementById('projectTitle').textContent = result.project.title;
                document.title = `CollabSpace Pro - ${result.project.title}`;
            }
        }

        // Render functions
        function render() {
            renderTeam();
            renderIdeas();
            updateStats();
        }

        function renderTeam() {
            const list = document.getElementById('teamList');
            
            if (teamMembers.length === 0) {
                list.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p><strong>No team members yet</strong><br>
                        Click the + button to add your first team member</p>
                    </div>
                `;
                return;
            }
            
            list.innerHTML = teamMembers.map(m => `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${m.name}</strong><br>
                        <small class="text-muted text-capitalize">${m.role}</small>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-${getStatusColor(m.status)} me-2">${m.status || 'active'}</span>
                        <div class="member-actions">
                            <button class="btn btn-sm btn-outline-primary me-1" onclick="editMember('${m.joined_id}')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteMember('${m.joined_id}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function renderIdeas() {
            const list = document.getElementById('ideasList');
            
            if (ideas.length === 0) {
                list.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-lightbulb"></i>
                        <p><strong>No ideas shared yet</strong><br>
                        Start by sharing your first innovative idea!</p>
                    </div>
                `;
                return;
            }
            
            list.innerHTML = ideas.map(i => `
                <div class="card mb-3 ${selectedIdeas.has(i.uid) ? 'border-primary' : ''}" data-idea-id="${i.uid}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center">
                                ${editMode ? `<input type="checkbox" class="form-check-input me-2" onchange="toggleIdeaSelection('${i.uid}')" ${selectedIdeas.has(i.uid) ? 'checked' : ''}>` : ''}
                                <small class="text-muted">${formatDate(i.created_on)}</small>
                            </div>
                            <div class="idea-actions">
                                <button class="btn btn-sm btn-outline-primary me-1" onclick="editIdea('${i.uid}')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteIdea('${i.uid}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <p class="card-text">${i.idea}</p>
                        <small class="text-primary">
                            <i class="fas fa-user"></i> Creator ID: ${i.creator_id}<br>
                            <i class="fas fa-tag"></i> UID: ${i.uid}
                        </small>
                    </div>
                </div>
            `).join('');
        }

        function updateStats() {
            document.getElementById('totalMembers').textContent = teamMembers.length;
            document.getElementById('totalIdeas').textContent = ideas.length;
        }

        // Member functions
       async function addMember(event) {
    event.preventDefault();
    
    const name = document.getElementById('memberName').value.trim();
    const role = document.getElementById('memberRole').value;
    const status = document.getElementById('memberStatus').value;
    
    if (!name) {
        showAlert('Name is required', 'danger');
        return;
    }
    
    const form = event.target;
    const submitBtn = form.querySelector('button[type="submit"]');
    const spinner = submitBtn.querySelector('.spinner-border');
    
    setLoading(submitBtn, spinner, true);
    
    try {
        const formData = new FormData();
        formData.append('action', 'add_member');
        formData.append('project_id', projectId);
        formData.append('joined_id', generateJoinedId());
        formData.append('name', name);
        formData.append('role', role);
        formData.append('status', status);

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });

        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (parseError) {
            console.error('Response text:', text);
            throw new Error('Invalid JSON response from server');
        }
        
        if (data.success) {
            teamMembers.push(data.member);
            render();
            const modal = bootstrap.Modal.getInstance(document.getElementById('addMemberModal'));
            modal.hide();
            event.target.reset();
            showAlert(data.message, 'success');
        } else {
            showAlert(data.message, 'danger');
        }
    } catch (error) {
        console.error('Error adding member:', error);
        showAlert('Error adding member: ' + error.message, 'danger');
    } finally {
        setLoading(submitBtn, spinner, false);
    }
}


        function editMember(joinedId) {
            const member = teamMembers.find(m => m.joined_id === joinedId);
            if (!member) return;

            document.getElementById('editMemberId').value = member.joined_id;
            document.getElementById('editMemberName').value = member.name;
            document.getElementById('editMemberRole').value = member.role;
            document.getElementById('editMemberStatus').value = member.status || 'active';

            new bootstrap.Modal(document.getElementById('editMemberModal')).show();
        }

        async function updateMember(e) {
            e.preventDefault();
            const form = e.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            const spinner = submitBtn.querySelector('.spinner-border');
            
            setLoading(submitBtn, spinner, true);

            const data = {
                joined_id: document.getElementById('editMemberId').value,
                name: document.getElementById('editMemberName').value,
                role: document.getElementById('editMemberRole').value,
                status: document.getElementById('editMemberStatus').value
            };

            const result = await apiCall('update_member', data);
            
            if (result.success) {
                // Update local data
                const memberIndex = teamMembers.findIndex(m => m.joined_id === data.joined_id);
                if (memberIndex !== -1) {
                    teamMembers[memberIndex] = { ...teamMembers[memberIndex], ...data };
                    render();
                }
                
                bootstrap.Modal.getInstance(document.getElementById('editMemberModal')).hide();
                showAlert(result.message, 'success');
            } else {
                showAlert(result.message, 'danger');
            }
            
            setLoading(submitBtn, spinner, false);
        }

        async function deleteMember(joinedId) {
            if (!confirm('Are you sure you want to remove this team member?')) return;

            const result = await apiCall('delete_member', { joined_id: joinedId });
            
            if (result.success) {
                teamMembers = teamMembers.filter(m => m.joined_id !== joinedId);
                render();
                showAlert(result.message, 'success');
            } else {
                showAlert(result.message, 'danger');
            }
        }

        // Idea functions
        async function addIdea(e) {
            e.preventDefault();
            const form = e.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            const spinner = submitBtn.querySelector('.spinner-border');
            
            setLoading(submitBtn, spinner, true);

            const data = {
                project_id: projectId,
                creator_id: currentUserId,
                idea: document.getElementById('ideaContent').value
            };

            const result = await apiCall('add_idea', data);
            
            if (result.success) {
                ideas.unshift(result.idea);
                render();
                bootstrap.Modal.getInstance(document.getElementById('newIdeaModal')).hide();
                form.reset();
                showAlert(result.message, 'success');
            } else {
                showAlert(result.message, 'danger');
            }
            
            setLoading(submitBtn, spinner, false);
        }

        function editIdea(uid) {
            const idea = ideas.find(i => i.uid === uid);
            if (!idea) return;

            document.getElementById('editIdeaId').value = idea.uid;
            document.getElementById('editIdeaContent').value = idea.idea;

            new bootstrap.Modal(document.getElementById('editIdeaModal')).show();
        }

        async function updateIdea(e) {
            e.preventDefault();
            const form = e.target;
            const submitBtn = form.querySelector('button[type="submit"]');
            const spinner = submitBtn.querySelector('.spinner-border');
            
            setLoading(submitBtn, spinner, true);

            const data = {
                uid: document.getElementById('editIdeaId').value,
                idea: document.getElementById('editIdeaContent').value
            };

            const result = await apiCall('update_idea', data);
            
            if (result.success) {
                // Update local data
                const ideaIndex = ideas.findIndex(i => i.uid === data.uid);
                if (ideaIndex !== -1) {
                    ideas[ideaIndex].idea = data.idea;
                    render();
                }
                
                bootstrap.Modal.getInstance(document.getElementById('editIdeaModal')).hide();
                showAlert(result.message, 'success');
            } else {
                showAlert(result.message, 'danger');
            }
            
            setLoading(submitBtn, spinner, false);
        }

        async function deleteIdea(uid) {
            if (!confirm('Are you sure you want to delete this idea?')) return;

            const result = await apiCall('delete_idea', { uid: uid });
            
            if (result.success) {
                ideas = ideas.filter(i => i.uid !== uid);
                selectedIdeas.delete(uid);
                render();
                updateDeleteButton();
                showAlert(result.message, 'success');
            } else {
                showAlert(result.message, 'danger');
            }
        }

        // Edit mode functions
        function toggleEditMode() {
            editMode = !editMode;
            selectedIdeas.clear();
            
            document.getElementById('editModeText').textContent = editMode ? 'Exit Edit' : 'Edit';
            document.getElementById('deleteBtn').disabled = true;
            
            render();
        }

        function toggleIdeaSelection(uid) {
            if (selectedIdeas.has(uid)) {
                selectedIdeas.delete(uid);
            } else {
                selectedIdeas.add(uid);
            }
            updateDeleteButton();
            render();
        }

        function updateDeleteButton() {
            document.getElementById('deleteBtn').disabled = selectedIdeas.size === 0;
        }

        async function deleteSelectedIdeas() {
            if (selectedIdeas.size === 0) return;
            
            if (!confirm(`Are you sure you want to delete ${selectedIdeas.size} selected idea(s)?`)) return;

            const deletePromises = Array.from(selectedIdeas).map(uid => 
                apiCall('delete_idea', { uid: uid })
            );

            const results = await Promise.all(deletePromises);
            const successful = results.filter(r => r.success).length;
            
            if (successful > 0) {
                // Remove successfully deleted ideas
                ideas = ideas.filter(i => !selectedIdeas.has(i.uid));
                selectedIdeas.clear();
                render();
                updateDeleteButton();
                showAlert(`Successfully deleted ${successful} idea(s)`, 'success');
            }
            
            if (successful < results.length) {
                showAlert(`Failed to delete ${results.length - successful} idea(s)`, 'warning');
            }
        }

        // AI Analysis functions
        function aiAnalyze(type) {
            const output = document.getElementById('aiOutput');
            const content = document.getElementById('aiOutputContent');
            
            const responses = {
                summarize: `Portfolio contains ${ideas.length} innovative ideas from ${teamMembers.length} team members. Key themes: Technology integration, process optimization, and user experience enhancement.`,
                analyze: `Strong innovation pipeline with ${ideas.length} concepts. Recommend prioritizing ideas with highest feasibility scores. Team expertise distribution: ${Math.round(teamMembers.length * 0.4)} technical, ${Math.round(teamMembers.length * 0.6)} strategic roles.`,
                expand: `Expansion opportunities: Consider cross-functional collaboration, prototype development, market validation studies, and stakeholder feedback sessions.`,
                gaps: `Analysis reveals potential gaps: Implementation timelines undefined, resource allocation unclear, risk assessment needed, success metrics to be established.`
            };
            
            content.textContent = responses[type];
            output.classList.remove('d-none');
        }

        function closeAiOutput() {
            document.getElementById('aiOutput').classList.add('d-none');
        }

        function downloadPDF() {
            // In a real implementation, this would generate and download a PDF
            showAlert('PDF report generated successfully! (Demo)', 'success');
        }

        // Utility functions
        function getStatusColor(status) {
            const colors = {
                'active': 'success',
                'inactive': 'secondary',
                'pending': 'warning'
            };
            return colors[status] || 'primary';
        }

        function formatDate(dateString) {
            try {
                const date = new Date(dateString);
                const now = new Date();
                const diffMs = now - date;
                const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
                
                if (diffDays === 0) {
                    const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
                    if (diffHours === 0) {
                        const diffMinutes = Math.floor(diffMs / (1000 * 60));
                        return diffMinutes <= 1 ? 'Just now' : `${diffMinutes} minutes ago`;
                    }
                    return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
                } else if (diffDays === 1) {
                    return 'Yesterday';
                } else if (diffDays < 7) {
                    return `${diffDays} days ago`;
                } else {
                    return date.toLocaleDateString();
                }
            } catch (e) {
                return dateString;
            }
        }

        function setLoading(button, spinner, loading) {
            if (loading) {
                button.disabled = true;
                spinner.classList.remove('d-none');
            } else {
                button.disabled = false;
                spinner.classList.add('d-none');
            }
        }

        function showAlert(message, type = 'info') {
            // Create alert element
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 80px; right: 20px; z-index: 9999; min-width: 300px;';
            
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Error handling for modals
        document.addEventListener('hidden.bs.modal', function (e) {
            // Reset form states when modals close
            const forms = e.target.querySelectorAll('form');
            forms.forEach(form => {
                form.reset();
                const spinners = form.querySelectorAll('.spinner-border');
                const buttons = form.querySelectorAll('button[type="submit"]');
                
                spinners.forEach(s => s.classList.add('d-none'));
                buttons.forEach(b => b.disabled = false);
            });
        });

        // Refresh data periodically (optional)
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                loadProjectData();
            }
        }, 30000); // Refresh every 30 seconds when page is visible
    </script>
</body>
</html>