<?php
// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$db = "colabspace";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'fetch_projects':
            fetchProjects($conn, $_POST['uid']);
            break;
        case 'create_project':
            createProject($conn, $_POST);
            break;
        case 'join_project':
            joinProject($conn, $_POST);
            break;
        case 'delete_joined_project':
            deleteJoinedProject($conn, $_POST);
            break;
        case 'delete_created_project':
            deleteCreatedProject($conn, $_POST);
            break;
    }
    exit;
}

function fetchProjects($conn, $uid) {
    // Fetch joined projects
    $joinedQuery = "SELECT pj.*, p.title, p.description, p.category 
                   FROM project_joined pj 
                   LEFT JOIN projects p ON pj.project_id = p.uid 
                   WHERE pj.joined_id = ?";
    $stmt = $conn->prepare($joinedQuery);
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $joinedResult = $stmt->get_result();
    $joinedProjects = $joinedResult->fetch_all(MYSQLI_ASSOC);
    
    // Fetch created projects
    $createdQuery = "SELECT * FROM projects WHERE creator_id = ?";
    $stmt = $conn->prepare($createdQuery);
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $createdResult = $stmt->get_result();
    $createdProjects = $createdResult->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'joined' => $joinedProjects,
        'created' => $createdProjects
    ]);
}

function createProject($conn, $data) {
    $uid = 'P' . time() . rand(1000, 9999);
    $title = $data['title'];
    $description = $data['description'];
    $category = $data['category'];
    $password = password_hash($data['password'], PASSWORD_DEFAULT);
    $creator_id = $data['creator_id'];
    $created_by = $data['created_by'];
    $status = 'active';
    
    $query = "INSERT INTO projects (uid, title, description, category, password, created_by, creator_id, status) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssssss", $uid, $title, $description, $category, $password, $created_by, $creator_id, $status);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'project_id' => $uid, 'message' => 'Project created successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create project']);
    }
}

function joinProject($conn, $data) {
    $project_id = $data['project_id'];
    $project_password = $data['password'];
    $uid = $data['uid'];
    $name = $data['name'];
    $username = $data['username'];
    $email = $data['email'];
    
    // First check if project exists and password is correct
    $checkQuery = "SELECT uid, password FROM projects WHERE uid = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param("s", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Project not found']);
        return;
    }
    
    $project = $result->fetch_assoc();
    if (!password_verify($project_password, $project['password'])) {
        echo json_encode(['success' => false, 'message' => 'Incorrect password']);
        return;
    }
    
    // Check if already joined
    $checkJoinedQuery = "SELECT joined_id FROM project_joined WHERE project_id = ? AND joined_id = ?";
    $stmt = $conn->prepare($checkJoinedQuery);
    $stmt->bind_param("ss", $project_id, $uid);
    $stmt->execute();
    $joinedResult = $stmt->get_result();
    
    if ($joinedResult->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Already joined this project']);
        return;
    }
    
    // Join the project
    // $joined_id = 'J' . time() . rand(1000, 9999);
    $insertQuery = "INSERT INTO project_joined (joined_id, name, username, project_id, email) 
                   VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param("sssss",$uid, $name, $username, $project_id, $email);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Successfully joined the project!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to join project']);
    }
}

function deleteJoinedProject($conn, $data) {
    $project_id = $data['project_id'];
    $uid = $data['uid'];
    
    $query = "DELETE FROM project_joined WHERE project_id = ? AND joined_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $project_id, $uid);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Left project successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to leave project']);
    }
}

function deleteCreatedProject($conn, $data) {
    $project_id = $data['project_id'];
    $uid = $data['uid'];
    
    // Delete from projects table
    $query = "DELETE FROM projects WHERE uid = ? AND creator_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $project_id, $uid);
    
    if ($stmt->execute()) {
        // Also delete all joined entries for this project
        $deleteJoinedQuery = "DELETE FROM project_joined WHERE project_id = ?";
        $stmt = $conn->prepare($deleteJoinedQuery);
        $stmt->bind_param("s", $project_id);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Project deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete project']);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>CollabSpace - Project Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
</head>
<body class="bg-light">

    <!-- Container for main content -->
    <div id="mainContent" style="display:none;">
        <!-- Simple Header -->
        <header class="bg-primary text-white py-4">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h1 class="h2 mb-1"><i class="fas fa-users me-2"></i>CollabSpace</h1>
                        <p class="mb-0">Simple Team Project Management</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge bg-light text-primary fs-6 px-3 py-2">
                            <i class="fas fa-user me-1"></i>Student Portal
                        </span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="container py-5">
            <!-- Introduction Section -->
            <div class="row mb-5">
                <div class="col-12">
                    <div class="text-center mb-4">
                        <h2 class="h3 mb-3">What is CollabSpace?</h2>
                        <p class="lead text-muted">A simple platform where students can create projects and collaborate with their classmates</p>
                    </div>
                    <div class="row justify-content-center">
                        <div class="col-md-8">
                            <div class="row text-center">
                                <div class="col-md-4 mb-3">
                                    <div class="card border-0 bg-transparent">
                                        <div class="card-body">
                                            <i class="fas fa-plus-circle fa-2x text-primary mb-2"></i>
                                            <h6>Create Projects</h6>
                                            <small class="text-muted">Start your own project</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card border-0 bg-transparent">
                                        <div class="card-body">
                                            <i class="fas fa-handshake fa-2x text-success mb-2"></i>
                                            <h6>Join Teams</h6>
                                            <small class="text-muted">Collaborate with others</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card border-0 bg-transparent">
                                        <div class="card-body">
                                            <i class="fas fa-lightbulb fa-2x text-warning mb-2"></i>
                                            <h6>Share Ideas</h6>
                                            <small class="text-muted">Brainstorm together</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Project Actions -->
            <div class="row g-4 mb-5">
                <!-- Join Project -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-sign-in-alt fa-3x text-success mb-3"></i>
                            <h5>Join a Project</h5>
                            <p class="text-muted">Enter project details to join an existing team</p>
                            <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#joinModal">
                                <i class="fas fa-plus me-2"></i>Join Project
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Create Project -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-rocket fa-3x text-primary mb-3"></i>
                            <h5>Create a Project</h5>
                            <p class="text-muted">Start your own project and invite team members</p>
                            <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#createModal">
                                <i class="fas fa-plus me-2"></i>Create Project
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Project Lists -->
            <div class="row g-4">
                <!-- Joined Projects -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="fas fa-list me-2"></i>Projects I Joined (<span id="joinedCount">0</span>)</h6>
                            <button class="btn btn-sm btn-light" onclick="loadProjects()">
                                <i class="fas fa-refresh"></i>
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="joinedList">
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                    <p>No projects joined yet</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Created Projects -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="fas fa-star me-2"></i>My Projects (<span id="createdCount">0</span>)</h6>
                            <button class="btn btn-sm btn-light" onclick="loadProjects()">
                                <i class="fas fa-refresh"></i>
                            </button>
                        </div>
                        <div class="card-body">
                            <div id="createdList">
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                    <p>No projects created yet</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Join Project Modal -->
        <div class="modal fade" id="joinModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Join Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form onsubmit="joinProject(event)">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Project ID</label>
                                <input type="text" class="form-control" id="joinId" placeholder="Enter project ID" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Project Password</label>
                                <input type="password" class="form-control" id="joinPassword" placeholder="Enter project password" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Your Name</label>
                                <input type="text" class="form-control" id="joinName" placeholder="Enter your name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" id="joinUsername" placeholder="Enter username" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" id="joinEmail" placeholder="Enter your email" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Join</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Create Project Modal -->
        <div class="modal fade" id="createModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Create Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form onsubmit="createProject(event)">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Project Title</label>
                                <input type="text" class="form-control" id="createTitle" placeholder="Enter project title" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" id="createDesc" rows="3" placeholder="Describe your project" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" id="createCategory" required>
                                    <option value="">Select category</option>
                                    <option value="web">Web Development</option>
                                    <option value="mobile">Mobile App</option>
                                    <option value="ai">Artificial Intelligence</option>
                                    <option value="data">Data Science</option>
                                    <option value="game">Game Development</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" id="createPassword" placeholder="Set project password" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Simple Alert -->
        <div class="position-fixed top-0 start-50 translate-middle-x mt-3" style="z-index: 1050;">
            <div class="alert alert-success alert-dismissible fade" id="successAlert" role="alert">
                <span id="alertMessage"></span>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    </div>

    <!-- Container for auth message -->
    <div id="authMessage" class="container py-5" style="display:none;">
        <div class="alert alert-warning text-center">
            <h4 class="alert-heading">Authentication Required</h4>
            <p>Please create your account first to access CollabSpace.</p>
            <hr>
            <a href="login.php" class="btn btn-primary">Go to Login</a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Check if user is authenticated
        const accountCreated = localStorage.getItem('accountCreated');
        const currentUserId = localStorage.getItem('uid');
        const currentUserName = localStorage.getItem('name') || 'User';

        if (accountCreated === 'true' && currentUserId) {
            // Show main content
            document.getElementById('mainContent').style.display = 'block';
            document.getElementById('authMessage').style.display = 'none';
            // Load projects on page load
            loadProjects();
        } else {
            // Show auth message
            document.getElementById('mainContent').style.display = 'none';
            document.getElementById('authMessage').style.display = 'block';
        }

        // Data storage
        let joinedProjects = [];
        let createdProjects = [];

        // Show alert message
        function showAlert(message, type = 'success') {
            const alert = document.getElementById('successAlert');
            const messageSpan = document.getElementById('alertMessage');
            
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            messageSpan.textContent = message;
            
            setTimeout(() => {
                alert.classList.remove('show');
            }, 3000);
        }

        // Update project counts
        function updateCounts() {
            document.getElementById('joinedCount').textContent = joinedProjects.length;
            document.getElementById('createdCount').textContent = createdProjects.length;
        }

        // Load projects from database
        async function loadProjects() {
            try {
                const formData = new FormData();
                formData.append('action', 'fetch_projects');
                formData.append('uid', currentUserId);

                console.log('Sending request with UID:', currentUserId); // Debug log

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                // Check if response is ok
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const text = await response.text();
                console.log('Server response:', text); // Debug log - check browser console
                
                // Try to parse as JSON
                let data;
                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response text:', text);
                    showAlert('Server returned invalid response. Check browser console for details.', 'danger');
                    return;
                }

                if (data.success === false) {
                    showAlert(data.message || 'Error loading projects', 'danger');
                    return;
                }

                joinedProjects = data.joined || [];
                createdProjects = data.created || [];

                renderJoined();
                renderCreated();
                updateCounts();
            } catch (error) {
                console.error('Error loading projects:', error);
                showAlert('Error loading projects: ' + error.message, 'danger');
            }
        }

        // Render joined projects
        function renderJoined() {
            const container = document.getElementById('joinedList');
            if (joinedProjects.length === 0) {
                container.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-2x mb-2"></i>
                        <p>No projects joined yet</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = joinedProjects.map(project => `
                <div class="border-bottom pb-2 mb-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="mb-1">${project.title || 'Project'}</h6>
                            <small class="text-muted d-block">Category: ${project.category || 'N/A'}</small>
                            <small class="text-muted">ID: ${project.project_id}</small>
                        </div>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-primary" onclick="enterProject('${project.project_id}', '${project.title}', 'joined')">
                                <i class="fas fa-sign-in-alt"></i> Enter
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="leaveProject('${project.project_id}')">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        // Render created projects
        function renderCreated() {
            const container = document.getElementById('createdList');
            if (createdProjects.length === 0) {
                container.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-2x mb-2"></i>
                        <p>No projects created yet</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = createdProjects.map(project => `
                <div class="border-bottom pb-2 mb-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="mb-1">${project.title}</h6>
                            <small class="text-muted d-block">${project.category}</small>
                            <small class="text-muted">ID: ${project.uid}</small>
                        </div>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-primary" onclick="enterProject('${project.uid}', '${project.title}', 'created')">
                                <i class="fas fa-cog"></i> Manage
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteProject('${project.uid}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        // Join project function
        async function joinProject(event) {
            event.preventDefault();
            
            const projectId = document.getElementById('joinId').value;
            const password = document.getElementById('joinPassword').value;
            const name = document.getElementById('joinName').value;
            const username = document.getElementById('joinUsername').value;
            const email = document.getElementById('joinEmail').value;
            
            try {
                const formData = new FormData();
                formData.append('action', 'join_project');
                formData.append('project_id', projectId);
                formData.append('password', password);
                formData.append('uid', currentUserId);
                formData.append('name', name);
                formData.append('username', username);
                formData.append('email', email);

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
                    const modal = bootstrap.Modal.getInstance(document.getElementById('joinModal'));
                    modal.hide();
                    event.target.reset();
                    showAlert(data.message);
                    loadProjects();
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error joining project:', error);
                showAlert('Error joining project: ' + error.message, 'danger');
            }
        }

        // Create project function
        async function createProject(event) {
            event.preventDefault();
            
            const title = document.getElementById('createTitle').value;
            const description = document.getElementById('createDesc').value;
            const category = document.getElementById('createCategory').value;
            const password = document.getElementById('createPassword').value;
            
            try {
                const formData = new FormData();
                formData.append('action', 'create_project');
                formData.append('title', title);
                formData.append('description', description);
                formData.append('category', category);
                formData.append('password', password);
                formData.append('creator_id', currentUserId);
                formData.append('created_by', currentUserName);

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
                    const modal = bootstrap.Modal.getInstance(document.getElementById('createModal'));
                    modal.hide();
                    event.target.reset();
                    showAlert(`${data.message} Project ID: ${data.project_id}`);
                    loadProjects();
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error creating project:', error);
                showAlert('Error creating project: ' + error.message, 'danger');
            }
        }

        // Leave project function
        async function leaveProject(projectId) {
            if (!confirm('Are you sure you want to leave this project?')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'delete_joined_project');
                formData.append('project_id', projectId);
                formData.append('uid', currentUserId);

                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                
                if (data.success) {
                    showAlert(data.message);
                    loadProjects();
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error leaving project:', error);
                showAlert('Error leaving project', 'danger');
            }
        }

        // Delete project function
        async function deleteProject(projectId) {
            if (!confirm('Are you sure you want to delete this project? This action cannot be undone and will remove all members from the project.')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'delete_created_project');
                formData.append('project_id', projectId);
                formData.append('uid', currentUserId);

                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                
                if (data.success) {
                    showAlert(data.message);
                    loadProjects();
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                console.error('Error deleting project:', error);
                showAlert('Error deleting project', 'danger');
            }
        }

        // Enter project function
        function enterProject(projectId, projectName, type) {
            showAlert(`Entering project: ${projectName}`);
            
            window.location.href = `project.php?projectid=${projectId}`;
            // if (type === 'joined') {
            //     window.location.href = `projects-joined.php?projectid=${projectId}`;
            // } else if (type === 'created') {
            //     window.location.href = `projects-created.php?projectid=${projectId}`;
            // }
        }
    </script>
</body>
</html>
