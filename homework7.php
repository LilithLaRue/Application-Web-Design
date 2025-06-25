<?php
// la causa de mi sufrimiento eterno

$subjects = [1 => 'Math', 2 => 'English', 3 => 'Science'];
$file = __DIR__ . '/activities.json';

function readActivities() {
    global $file;
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

function writeActivities($acts) {
    global $file;
    file_put_contents($file, json_encode($acts));
}

$action = $_GET['action'] ?? 'list';
$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : null;
$activity_id = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : null;
$activities = readActivities();

function nextId($activities) {
    if (!$activities) return 1;
    return max(array_column($activities, 'id')) + 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' && $subject_id) {
        $activities[] = [
            'id' => nextId($activities),
            'subject_id' => $subject_id,
            'type' => $_POST['type'] ?? '',
            'grade' => $_POST['grade'] ?? '',
            'date' => $_POST['date'] ?? '',
            'space' => $_POST['space'] ?? '',
        ];
        writeActivities($activities);
        header("Location: ?action=view&subject_id=$subject_id");
        exit;
    }
    if ($action === 'edit' && $subject_id && $activity_id) {
        foreach ($activities as &$a) {
            if ($a['id'] == $activity_id) {
                $a['type'] = $_POST['type'] ?? '';
                $a['grade'] = $_POST['grade'] ?? '';
                $a['date'] = $_POST['date'] ?? '';
                $a['space'] = $_POST['space'] ?? '';
                break;
            }
        }
        writeActivities($activities);
        header("Location: ?action=view&subject_id=$subject_id");
        exit;
    }
}

if ($action === 'delete' && $subject_id && $activity_id) {
    $activities = array_filter($activities, fn($a) => $a['id'] != $activity_id);
    writeActivities(array_values($activities));
    header("Location: ?action=view&subject_id=$subject_id");
    exit;
}

echo "<h1>Subjects</h1>";

if ($action === 'list') {
    foreach ($subjects as $id => $name) {
        echo "<p><a href='?action=view&subject_id=$id'>$name</a></p>";
    }
    exit;
}

if ($action === 'view' && $subject_id && isset($subjects[$subject_id])) {
    echo "<p><a href='?'>Back to subjects</a></p>";
    echo "<h2>{$subjects[$subject_id]}</h2>";
    echo "<p><a href='?action=add&subject_id=$subject_id'>Add activity</a></p>";
    echo "<table border=1 cellpadding=5><tr><th>Type</th><th>Grade</th><th>Date</th><th>Space</th><th>Actions</th></tr>";
    foreach ($activities as $a) {
        if ($a['subject_id'] == $subject_id) {
            echo "<tr>";
            echo "<td>{$a['type']}</td>";
            echo "<td>{$a['grade']}</td>";
            echo "<td>{$a['date']}</td>";
            echo "<td>{$a['space']}</td>";
            echo "<td>
                <a href='?action=edit&subject_id=$subject_id&activity_id={$a['id']}'>Edit</a> |
                <a href='?action=delete&subject_id=$subject_id&activity_id={$a['id']}' onclick='return confirm(\"Delete?\")'>Delete</a>
                </td>";
            echo "</tr>";
        }
    }
    echo "</table>";
    exit;
}

if ($action === 'add' && $subject_id && isset($subjects[$subject_id])) {
    echo "<p><a href='?action=view&subject_id=$subject_id'>Back</a></p>";
    echo "<h2>Add Activity for {$subjects[$subject_id]}</h2>";
    echo "<form method='post'>
        Type: <input name='type'><br>
        Grade: <input name='grade'><br>
        Date: <input type='date' name='date'><br>
        Space: <input name='space'><br>
        <button>Add</button>
    </form>";
    exit;
}

if ($action === 'edit' && $subject_id && $activity_id && isset($subjects[$subject_id])) {
    $act = null;
    foreach ($activities as $a) {
        if ($a['id'] == $activity_id) {
            $act = $a;
            break;
        }
    }
    if (!$act) {
        echo "Activity not found";
        exit;
    }
    echo "<p><a href='?action=view&subject_id=$subject_id'>Back</a></p>";
    echo "<h2>Edit Activity for {$subjects[$subject_id]}</h2>";
    echo "<form method='post'>
        Type: <input name='type' value='".htmlspecialchars($act['type'])."'><br>
        Grade: <input name='grade' value='".htmlspecialchars($act['grade'])."'><br>
        Date: <input type='date' name='date' value='".htmlspecialchars($act['date'])."'><br>
        Space: <input name='space' value='".htmlspecialchars($act['space'])."'><br>
        <button>Update</button>
    </form>";
    exit;
}

header("Location: ?");
exit;
