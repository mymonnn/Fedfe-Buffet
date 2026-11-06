<?php
/** แสดงโต๊ะหนึ่งใบ ใช้ร่วมกันในหน้าโต๊ะและคำตอบ Ajax
 * @param array $table ข้อมูลโต๊ะและรอบบริการจากฐานข้อมูล
 */
function render_table_card(array $table): void {
$table_version = hash('sha256', implode('|', [
    $table['table_number'], $table['table_open'], $table['visit_id'] ?? '',
    $table['people_amount'] ?? '', $table['created_at'] ?? '',
    $table['need_help'] ?? '', $table['request_bill'] ?? ''
]));
?>
<article id="table-<?= h($table['table_number']) ?>" class="table-card <?= $table['visit_id'] ? 'is-open' : 'is-closed' ?>" data-version="<?= h($table_version) ?>">
    <h3>โต๊ะ <?= h($table['table_number']) ?></h3>
    <p class="notice" role="status" data-table-message hidden></p>
    <?php if ($table['visit_id']): ?>
        <p><span class="status open">กำลังให้บริการ</span> <strong><?= h($table['people_amount']) ?> คน</strong></p>
        <p class="muted">เปิดเมื่อ <?= h(date('H:i', strtotime($table['created_at']))) ?></p>
        <?php if ($table['request_bill']): ?>
            <p class="notice warning">ขอเช็คบิลแล้ว · ระงับการสั่งเพิ่ม</p>
        <?php endif; ?>
        <details class="guest-edit">
            <summary>แก้ไขจำนวนลูกค้า</summary>
            <form action="<?= h(BASE_URL) ?>/actions/table.php" method="post" data-table-action>
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="guests">
                <input type="hidden" name="table_number" value="<?= h($table['table_number']) ?>">
                <input type="hidden" name="visit_id" value="<?= h($table['visit_id']) ?>">
                <div class="field">
                    <label for="guests-<?= h($table['table_number']) ?>">จำนวนลูกค้า</label>
                    <input id="guests-<?= h($table['table_number']) ?>" type="number" name="people_amount" min="1" max="99" value="<?= h($table['people_amount']) ?>" required>
                </div>
                <button type="submit" class="button secondary small">บันทึกจำนวน</button>
            </form>
        </details>
        <p class="card-actions"><a class="button" href="<?= h(BASE_URL) ?>/staff/bill.php?visit=<?= h($table['visit_id']) ?>">ดูบิลและปิดโต๊ะ</a></p>
        <?php if ($table['need_help']): ?>
            <form action="<?= h(BASE_URL) ?>/actions/table.php" method="post" class="inline-form" data-table-action>
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="ack">
                <input type="hidden" name="table_number" value="<?= h($table['table_number']) ?>">
                <input type="hidden" name="visit_id" value="<?= h($table['visit_id']) ?>">
                <button type="submit" class="button secondary small">รับทราบการเรียกพนักงาน</button>
            </form>
        <?php endif; ?>
        <?php if ($table['request_bill']): ?>
            <form action="<?= h(BASE_URL) ?>/actions/table.php" method="post" class="inline-form" data-table-action data-confirm="ยกเลิกการขอเช็คบิลและให้โต๊ะนี้สั่งอาหารต่อหรือไม่?">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="resume">
                <input type="hidden" name="table_number" value="<?= h($table['table_number']) ?>">
                <input type="hidden" name="visit_id" value="<?= h($table['visit_id']) ?>">
                <button type="submit" class="button secondary small">ให้สั่งอาหารต่อ</button>
            </form>
        <?php endif; ?>
        <p><a href="<?= h(BASE_URL) ?>/?table=<?= h($table['table_number']) ?>" target="_blank" rel="noopener">เปิดหน้าลูกค้าของโต๊ะนี้</a></p>
    <?php else: ?>
        <p><span class="status closed">ปิดอยู่</span></p>
        <form action="<?= h(BASE_URL) ?>/actions/table.php" method="post" class="open-table-form" data-table-action>
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="open">
            <input type="hidden" name="table_number" value="<?= h($table['table_number']) ?>">
            <div class="field">
                <label for="guests-<?= h($table['table_number']) ?>">จำนวนลูกค้า</label>
                <input id="guests-<?= h($table['table_number']) ?>" type="number" name="people_amount" min="1" max="99" placeholder="จำนวนคน" required>
            </div>
            <button type="submit" class="button">เปิดโต๊ะ</button>
        </form>
    <?php endif; ?>
</article>
<?php
}
