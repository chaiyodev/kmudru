<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$pdo = get_pdo();
$doc_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$version_id = isset($_GET['version_id']) ? (int) $_GET['version_id'] : 0;

if ($doc_id <= 0) {
    die("ไม่ระบุเอกสาร");
}

// Fetch Document Basic Info
$stmt = $pdo->prepare("SELECT title, type FROM documents WHERE id = ?");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch();

if (!$doc || $doc['type'] !== 'wiki') {
    die("ไม่พบข้อมูล Wiki");
}

// Fetch All Versions
$v_stmt = $pdo->prepare("SELECT v.*, u.full_name as editor_name 
                        FROM document_versions v 
                        JOIN users u ON v.user_id = u.id 
                        WHERE v.document_id = ? 
                        ORDER BY v.created_at DESC");
$v_stmt->execute([$doc_id]);
$versions = $v_stmt->fetchAll();

// If viewing a specific version
$selected_version = null;
if ($version_id > 0) {
    foreach ($versions as $v) {
        if ($v['id'] === $version_id) {
            $selected_version = $v;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการแก้ไข:
        <?php echo htmlspecialchars($doc['title']); ?> | UDRU Wisdom
    </title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sarabun:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .history-layout {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 2rem;
            align-items: start;
        }

        .version-list {
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .version-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: 0.2s;
            display: block;
            text-decoration: none;
            color: inherit;
        }

        .version-item:hover {
            background: #f8fafc;
        }

        .version-item.active {
            background: hsl(var(--primary)/0.05);
            border-left: 4px solid var(--teal-primary);
        }

        .version-date {
            font-size: 0.8125rem;
            color: hsl(var(--muted-foreground));
            margin-bottom: 0.25rem;
        }

        .version-editor {
            font-weight: 700;
            font-size: 0.9375rem;
            margin-bottom: 0.25rem;
        }

        .version-summary {
            font-size: 0.875rem;
            color: #475569;
            font-style: italic;
        }

        .version-content-card {
            background: white;
            border-radius: 1rem;
            border: 1px solid var(--border-color);
            padding: 2.5rem;
        }

        .snapshot-box {
            background: #f1f5f9;
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 2rem;
            font-family: inherit;
            white-space: pre-wrap;
            line-height: 1.7;
            border: 1px solid #e2e8f0;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-viewport">
            <header class="header-top">
                <div class="page-title">
                    <h2>ประวัติการแก้ไข (Revision History)</h2>
                    <p>
                        <?php echo htmlspecialchars($doc['title']); ?>
                    </p>
                </div>
                <div class="header-actions">
                    <a href="view.php?id=<?php echo $doc_id; ?>" class="btn-primary"
                        style="background: white; color: hsl(var(--foreground)); border: 1px solid var(--border-color);">
                        <i data-lucide="arrow-left"></i> กลับไปที่บทความ
                    </a>
                </div>
            </header>

            <div class="history-layout">
                <aside class="version-list">
                    <div
                        style="padding: 1rem 1.5rem; background: #f8fafc; font-weight: 800; font-size: 0.75rem; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; border-bottom: 1px solid var(--border-color);">
                        รายการเวอร์ชัน
                    </div>
                    <?php if (empty($versions)): ?>
                        <div style="padding: 2rem; text-align: center; color: #94a3b8;">ไม่มีประวัติการแก้ไข</div>
                    <?php endif; ?>
                    <?php foreach ($versions as $v): ?>
                        <a href="wiki_history.php?id=<?php echo $doc_id; ?>&version_id=<?php echo $v['id']; ?>"
                            class="version-item <?php echo ($version_id == $v['id']) ? 'active' : ''; ?>">
                            <div class="version-date">
                                <?php echo date('d M Y, H:i', strtotime($v['created_at'])); ?>
                            </div>
                            <div class="version-editor">
                                <?php echo htmlspecialchars($v['editor_name']); ?>
                            </div>
                            <div class="version-summary">
                                <?php echo htmlspecialchars($v['edit_summary'] ?: 'ไม่มีคำอธิบาย'); ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </aside>

                <section>
                    <?php if ($selected_version): ?>
                        <div class="version-content-card">
                            <div
                                style="margin-bottom: 2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1.5rem;">
                                <h3 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem;">
                                    <?php echo htmlspecialchars($selected_version['title_snapshot']); ?>
                                </h3>
                                <div style="display: flex; gap: 1rem; color: #64748b; font-size: 0.875rem;">
                                    <span>แก้ไขเมื่อ:
                                        <?php echo date('d/m/Y H:i', strtotime($selected_version['created_at'])); ?>
                                    </span>
                                    <span>โดย:
                                        <?php echo htmlspecialchars($selected_version['editor_name']); ?>
                                    </span>
                                </div>
                            </div>

                            <div style="margin-bottom: 1rem; font-weight: 700; color: #1e293b;">เนื้อหาในเวอร์ชันนี้:</div>
                            <div class="snapshot-box">
                                <?php echo htmlspecialchars($selected_version['content_snapshot']); ?>
                            </div>

                            <?php if (!empty($selected_version['references_snapshot'])): ?>
                                <div style="margin-bottom: 1rem; font-weight: 700; color: #1e293b;">แหล่งอ้างอิงในเวอร์ชันนี้:
                                </div>
                                <div class="snapshot-box" style="background: #f8fafc;">
                                    <?php echo htmlspecialchars($selected_version['references_snapshot']); ?>
                                </div>
                            <?php endif; ?>

                            <div
                                style="background: hsl(var(--primary)/0.05); padding: 1rem; border-radius: 0.5rem; border: 1px dashed var(--teal-primary); font-size: 0.875rem; color: var(--teal-primary);">
                                <strong>หมายเหตุ:</strong> นี่คือการแสดงผลข้อมูลดิบในไฟล์สำรอง (Snapshot)
                                เพื่อความถูกต้องของการเก็บประวัติครับ
                            </div>
                        </div>
                    <?php else: ?>
                        <div
                            style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 5rem; text-align: center; color: #94a3b8;">
                            <i data-lucide="history"
                                style="width: 48px; height: 48px; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <p>เลือกเวอร์ชันจากด้านซ้ายเพื่อดูรายละเอียดการแก้ไขครับ</p>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>

</html>