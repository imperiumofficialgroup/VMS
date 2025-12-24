<style>
  body {
    font-family: sans-serif;
    font-size: 12px;
    position: relative;
  }
  .watermark {
    position: fixed;
    top: 45%;
    left: 30%;
    font-size: 50px;
    color: rgba(200, 200, 200, 0.15);
    transform: rotate(-30deg);
    z-index: -1;
  }
  .page-number {
    position: fixed;
    bottom: 0;
    right: 0;
    font-size: 10px;
    color: #aaa;
  }
  h2 { margin-top: 20px; color: #003366; }
</style>

<div class="watermark">(mperiumTrust</div>

<h1><?= $data['event_name'] ?></h1>
<p><strong>Date & Time:</strong> <?= $data['date_time'] ?></p>
<p><strong>Location:</strong> <?= $data['location'] ?></p>

<?php foreach ($data as $key => $val): ?>
  <?php if (!in_array($key, ['event_name', 'location', 'date_time'])): ?>
    <h2><?= ucwords(str_replace('_', ' ', $key)) ?></h2>
    <p><?= nl2br($val) ?></p>
  <?php endif; ?>
<?php endforeach; ?>

<?php if (!empty($uploadedImages)): ?>
  <h2>Attached Images</h2>
  <?php foreach ($uploadedImages as $img): ?>
    <img src="../uploads/reports/<?= $img ?>" width="300" style="margin-bottom: 10px;" />
  <?php endforeach; ?>
<?php endif; ?>

<script type="text/php">
if (isset($pdf)) {
    $pdf->page_script('
        if ($PAGE_COUNT > 1) {
            $font = $fontMetrics->get_font("Arial, Helvetica, sans-serif", "normal");
            $size = 9;
            $pdf->text(500, 820, "Page $PAGE_NUM of $PAGE_COUNT", $font, $size);
        }
    ');
}
</script>
