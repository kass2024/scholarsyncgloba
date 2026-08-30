<?php
declare(strict_types=1);
/** @var array<int,array<string,mixed>> $rows */
/** @var string $heading */
/** @var string $sub */
/** @var string $section_class extra classes on section */
if (empty($rows) || !is_array($rows)) {
    return;
}
$section_class = $section_class ?? '';
?>
<section class="pcvc-testimonials-block <?php echo htmlspecialchars($section_class, ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="pcvc-testimonials-heading">
  <div class="pcvc-testimonials-inner">
    <header class="pcvc-testimonials-head">
      <h2 class="pcvc-testimonials-title" id="pcvc-testimonials-heading"><?php echo htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'); ?></h2>
      <?php if ($sub !== ''): ?>
        <p class="pcvc-testimonials-sub"><?php echo htmlspecialchars($sub, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php endif; ?>
    </header>
    <div class="pcvc-testimonials-grid">
      <?php foreach ($rows as $t): ?>
        <article class="pcvc-testimonial-card">
          <div class="pcvc-testimonial-photo-wrap">
            <?php if (!empty($t['photo_path'])): ?>
              <img src="<?php echo htmlspecialchars($t['photo_path'], ENT_QUOTES, 'UTF-8'); ?>"
                   alt=""
                   class="pcvc-testimonial-photo"
                   width="160"
                   height="160"
                   loading="lazy"
                   decoding="async">
            <?php else: ?>
              <div class="pcvc-testimonial-photo-fallback" aria-hidden="true"></div>
            <?php endif; ?>
          </div>
          <div class="pcvc-testimonial-body">
            <blockquote class="pcvc-testimonial-quote"><?php echo htmlspecialchars($t['quote'] ?? '', ENT_QUOTES, 'UTF-8'); ?></blockquote>
            <footer class="pcvc-testimonial-meta">
              <cite class="pcvc-testimonial-name"><?php echo htmlspecialchars($t['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></cite>
              <?php if (!empty($t['role_title'])): ?>
                <span class="pcvc-testimonial-role"><?php echo htmlspecialchars($t['role_title'], ENT_QUOTES, 'UTF-8'); ?></span>
              <?php endif; ?>
            </footer>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
