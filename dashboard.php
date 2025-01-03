<?php
$mysqli = new mysqli("localhost", "root", "", "family_tree");

// Check connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Add a new family member
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_member'])) {
    $name = trim($_POST['name']);
    $parent_id = $_POST['parent_id'] ?: null;

    if (!empty($name)) {
        $stmt = $mysqli->prepare("INSERT INTO family_members (name, parent_id) VALUES (?, ?)");
        $stmt->bind_param("si", $name, $parent_id);
        if ($stmt->execute()) {
            header("Location: dashboard.php");
            exit;
        } else {
            echo "Error adding member: " . $mysqli->error;
        }
    } else {
        echo "Name cannot be empty.";
    }
}

// Edit an existing family member
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_member'])) {
    $id = $_POST['id'];
    $name = trim($_POST['name']);
    $parent_id = $_POST['parent_id'] ?: null;

    if (!empty($name)) {
        $stmt = $mysqli->prepare("UPDATE family_members SET name = ?, parent_id = ? WHERE id = ?");
        $stmt->bind_param("sii", $name, $parent_id, $id);
        if ($stmt->execute()) {
            header("Location: dashboard.php");
            exit;
        } else {
            echo "Error editing member: " . $mysqli->error;
        }
    } else {
        echo "Name cannot be empty.";
    }
}

// Delete a family member
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];

    $stmt = $mysqli->prepare("DELETE FROM family_members WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: dashboard.php");
        exit;
    } else {
        echo "Error deleting member: " . $mysqli->error;
    }
}

// Fetch all family members
$members = $mysqli->query("SELECT * FROM family_members ORDER BY id ASC");

// Fetch family tree iteratively
function fetchTreeIteratively($mysqli) {
    $result = $mysqli->query("SELECT * FROM family_members");
    $nodes = $result->fetch_all(MYSQLI_ASSOC);
    $tree = [];

    // Build a reference map
    $map = [];
    foreach ($nodes as &$node) {
        $node['children'] = [];
        $map[$node['id']] = &$node;
    }

    // Create the tree structure
    foreach ($nodes as &$node) {
        if ($node['parent_id'] === null) {
            $tree[] = &$node;
        } else {
            $map[$node['parent_id']]['children'][] = &$node;
        }
    }

    return $tree;
}

$family_tree = fetchTreeIteratively($mysqli);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Family Tree Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f4f4f4;
        }

        ul.tree {
            display: flex;
            flex-direction: column;
            align-items: center;
            list-style-type: none;
            padding: 0;
            position: relative;
        }

        ul.tree::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            width: 0;
            height: 20px;
            border-left: 1px solid #ccc;
        }

        li {
            margin: 20px;
            text-align: center;
            position: relative;
        }

        li::before, li::after {
            content: '';
            position: absolute;
            top: 0;
            border-top: 1px solid #ccc;
            width: 50%;
            height: 20px;
        }

        li::before {
            right: 50%;
            border-right: 1px solid #ccc;
        }

        li::after {
            left: 50%;
            border-left: 1px solid #ccc;
        }

        li:only-child::before, li:only-child::after {
            display: none;
        }

        li:first-child::before, li:last-child::after {
            border: none;
        }

        li:only-child {
            padding-top: 0;
        }

        .node {
            display: inline-block;
            padding: 10px 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #f9f9f9;
        }

        .node:hover {
            background-color: #e6f7ff;
        }
    </style>
</head>
<body>
    <h1>Family Tree Dashboard</h1>

    <h2>Add Family Member</h2>
    <form method="POST">
        <label>Member Name:</label>
        <input type="text" name="name" required>
        <br>
        <label>Parent ID (leave blank if root):</label>
        <input type="number" name="parent_id">
        <br>
        <button type="submit" name="add_member">Add Member</button>
    </form>

    <h2>Family Members</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Parent ID</th>
            <th>Actions</th>
        </tr>
        <?php while ($row = $members->fetch_assoc()) { ?>
        <tr>
            <td><?= $row['id'] ?></td>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td><?= $row['parent_id'] ?: 'None' ?></td>
            <td>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                    <input type="text" name="name" value="<?= htmlspecialchars($row['name']) ?>" required>
                    <input type="number" name="parent_id" value="<?= $row['parent_id'] ?>" placeholder="Parent ID">
                    <button type="submit" name="edit_member">Edit</button>
                </form>
                <a href="dashboard.php?delete_id=<?= $row['id'] ?>" onclick="return confirm('Are you sure?')">Delete</a>
            </td>
        </tr>
        <?php } ?>
    </table>

    <h2>Family Tree</h2>
    <ul class="tree">
        <?php
        function renderTree($tree) {
            foreach ($tree as $node) {
                echo "<li><div class='node'>" . htmlspecialchars($node['name']) . "</div>";
                if (!empty($node['children'])) {
                    echo "<ul class='tree'>";
                    renderTree($node['children']);
                    echo "</ul>";
                }
                echo "</li>";
            }
        }

        if (!empty($family_tree)) {
            renderTree($family_tree);
        } else {
            echo "<li>No family members found.</li>";
        }
        ?>
    </ul>
</body>
</html>
