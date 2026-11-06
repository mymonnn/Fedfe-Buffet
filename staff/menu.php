<?php
    require_once "../connection.php";
    require_once "../functions.php";
    require_once "../includes/auth.php";
    require_once "../includes/icons.php";
    require_once "../includes/header.php";
    require_once "../includes/footer.php";
    $employee = require_employee($pdo);

    $editing = ['id' => 0, 'name' => '', 'category' => '', 'price' => '0.00', 'description' => '', 'is_available' => 1];
    if (is_manager($employee) && isset($_GET['edit'])) {
        $edit_id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
        $statement = $pdo->prepare('SELECT * FROM `MENU` WHERE id = ? AND is_deleted = FALSE');
        $statement->execute([$edit_id ?: 0]);
        $editing = $statement->fetch();
        if (!$editing) {
            flash('ไม่พบอาหารที่ต้องการแก้ไข', 'error');
            redirect_to('staff/menu.php');
        }
    }
    $form_data = $_SESSION['menu_form'] ?? null;
    unset($_SESSION['menu_form']);
    $form_error = is_array($form_data) && (int) $form_data['id'] === (int) $editing['id'];
    if ($form_error) {
        $fields = ['name', 'category', 'price', 'description', 'is_available'];
        for ($i = 0; $i < count($fields); $i++) {
            if (array_key_exists($fields[$i], $form_data)) {
                $editing[$fields[$i]] = $form_data[$fields[$i]];
            }
        }
    }
    $menu_statement = $pdo->query("SELECT * FROM `MENU` WHERE is_deleted = FALSE
        ORDER BY CASE category
            WHEN 'เนื้อ' THEN 1
            WHEN 'หมู' THEN 2
            WHEN 'ไก่' THEN 3
            WHEN 'ทะเล' THEN 4
            WHEN 'ผักและเห็ด' THEN 5
            WHEN 'เส้นและข้าว' THEN 6
            WHEN 'น้ำซุปและน้ำจิ้ม' THEN 7
            WHEN 'ของหวาน' THEN 8
            WHEN 'เครื่องดื่ม' THEN 9
            ELSE 10 END, category, id");
    $category_list = menu_categories($pdo);
    $selected_category = in_array($editing['category'], $category_list, true) ? $editing['category'] : '';
    $page_title = 'จัดการอาหาร';
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
?>



<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($page_title) ?> | เฟ็ดเฟ่ บุฟเฟต์</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/staff.css">
    <script src="../script/staff.js" defer></script>
</head>

<body class="staff">
    <?php render_header('staff', $employee, 0, false); ?>
    <main class="container">
        <?php if ($flash): ?>
            <p role="status" data-flash class="notice <?= h($flash['type']) ?>"><?= h($flash['message']) ?></p>
        <?php endif; ?>
        <header class="page-heading">
            <h1>จัดการอาหาร</h1>
            <p>พนักงานเปลี่ยนสถานะพร้อมเสิร์ฟได้ ผู้จัดการเพิ่ม แก้ไข และลบรายการอาหารได้</p>
        </header>

        <?php if (is_manager($employee)): ?>
            <details class="panel management-form" id="menu-form" <?= $editing['id'] || $form_error ? 'open' : '' ?>>
                <summary><?= icon($editing['id'] ? 'edit' : 'plus') ?> <?= $editing['id'] ? 'แก้ไขรายการอาหาร: ' . h($editing['name']) : 'เพิ่มรายการอาหาร' ?></summary>
                <form action="<?= h(BASE_URL) ?>/actions/menu.php" method="post" enctype="multipart/form-data" data-submit-once>
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="menu_id" value="<?= h($editing['id']) ?>">
                    <div class="form-row">
                        <div class="field">
                            <label for="food-name">ชื่ออาหาร</label>
                            <input id="food-name" name="name" value="<?= h($editing['name']) ?>" maxlength="120" required>
                        </div>
                        <div class="field">
                            <label for="food-category">หมวดหมู่</label>
                            <select id="food-category" name="category" required>
                                <option value="" disabled <?= $selected_category === '' ? 'selected' : '' ?>>เลือกหมวดหมู่</option>
                                <?php for ($i = 0; $i < count($category_list); $i++): ?>
                                    <option value="<?= h($category_list[$i]) ?>" <?= $selected_category === $category_list[$i] ? 'selected' : '' ?>><?= h($category_list[$i]) ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="food-price">ราคาเพิ่มต่อจาน (0 = รวมในบุฟเฟ่ต์)</label>
                            <input id="food-price" type="number" name="price" min="0" max="999999.99" step="0.01" value="<?= h($editing['price']) ?>" required>
                        </div>
                    </div>
                    <div class="field">
                        <label for="food-description">รายละเอียด (ไม่บังคับ)</label>
                        <textarea id="food-description" name="description" maxlength="500" rows="2"><?= h($editing['description']) ?></textarea>
                    </div>
                    <?php if ($editing['id']): ?>
                        <figure class="menu-image-preview">
                            <img src="<?= h(menu_image_url((int) $editing['id'])) ?>" alt="รูปอาหารปัจจุบัน" width="144" height="108" data-fallback="<?= h(BASE_URL) ?>/images/placeholder.svg">
                            <figcaption class="small-text muted">รูปปัจจุบัน</figcaption>
                        </figure>
                    <?php endif; ?>
                    <div class="field">
                        <label for="food-image">อัปโหลดรูปอาหาร (ไม่บังคับ)</label>
                        <input id="food-image" type="file" name="food_image" accept="image/jpeg,.jpg,.jpeg" aria-describedby="food-image-help">
                        <p id="food-image-help" class="small-text muted">
                            ไฟล์ JPG ขนาดไม่เกิน 2 MB
                            <?php if ($editing['id']): ?>เว้นว่างเพื่อใช้รูปเดิม<?php endif; ?>
                            <?php if ($form_error): ?>หากต้องการอัปโหลด ให้เลือกไฟล์อีกครั้ง<?php endif; ?>
                        </p>
                    </div>
                    <p><label><input type="checkbox" name="is_available" value="1" <?= $editing['is_available'] ? 'checked' : '' ?>> พร้อมเสิร์ฟ</label></p>
                    <button type="submit" class="button"><?= $editing['id'] ? 'บันทึกการแก้ไข' : 'เพิ่มอาหาร' ?></button>
                    <?php if ($editing['id']): ?><a class="button secondary" href="<?= h(BASE_URL) ?>/staff/menu.php">ยกเลิกการแก้ไข</a><?php endif; ?>
                </form>
            </details>
        <?php endif; ?>

        <section class="panel" id="menu-list" aria-labelledby="menu-list-heading">
            <h2 id="menu-list-heading">รายการอาหาร</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">ชื่ออาหาร</th>
                            <th scope="col">หมวดหมู่</th>
                            <th scope="col" class="text-right">ราคาเพิ่ม</th>
                            <th scope="col">สถานะ</th>
                            <th scope="col">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $menu_count = 0; ?>
                        <?php while ($food = $menu_statement->fetch()): ?>
                            <?php $menu_count++; ?>
                            <tr id="food-<?= h($food['id']) ?>">
                                <td><?= h($food['name']) ?></td>
                                <td><?= h($food['category']) ?></td>
                                <td class="text-right"><?= h(money($food['price'])) ?></td>
                                <td><span class="status <?= $food['is_available'] ? 'open' : 'closed' ?>"><?= $food['is_available'] ? 'พร้อมเสิร์ฟ' : 'หมดชั่วคราว' ?></span></td>
                                <td class="row-actions">
                                    <form action="<?= h(BASE_URL) ?>/actions/menu.php" method="post" class="inline-form" data-food-availability>
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="action" value="availability">
                                        <input type="hidden" name="menu_id" value="<?= h($food['id']) ?>">
                                        <input type="hidden" name="is_available" value="<?= $food['is_available'] ? '0' : '1' ?>">
                                        <button type="submit" class="button secondary small"><?= $food['is_available'] ? 'หมด' : 'พร้อมเสิร์ฟ' ?></button>
                                    </form>
                                    <?php if (is_manager($employee)): ?>
                                        <a class="button secondary small" href="<?= h(BASE_URL) ?>/staff/menu.php?edit=<?= h($food['id']) ?>#menu-form">แก้ไข</a>
                                        <form action="<?= h(BASE_URL) ?>/actions/menu.php" method="post" class="inline-form" data-confirm="<?= h('ลบ ' . $food['name'] . ' ออกจากเมนูหรือไม่?') ?>">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="menu_id" value="<?= h($food['id']) ?>">
                                            <button type="submit" class="button danger small">ลบ</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        <?php if ($menu_count === 0): ?><tr>
                                <td colspan="5" class="empty-state">ยังไม่มีรายการอาหาร</td>
                            </tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
    <?php render_footer('staff', '', '', 0, false); ?>
</body>

</html>
