<?php
/*
 * Refund mode: needs $refund and $u, shows one tab per conversation this user may open.
 * Direct mode: set $direct = ['id' => conversation id, 'note' => 'text'] before including.
 */
$isDirect = isset($direct);
$threads = [];
$unread = [];
$start = 'direct';
if (!$isDirect) {
    $threads = chat_threads_for($refund, $u);
    if (!$threads) return;
    $unread = unread_counts((int)$refund['id'], $u);
    $start = (string)array_key_first($threads);
    $want = (string)($_GET['thread'] ?? '');
    if (isset($threads[$want])) {
        $start = $want;
    } else {
        foreach ($threads as $k => $_) { if (!empty($unread[$k])) { $start = $k; break; } }
    }
}
?>
<section class="chat<?= $start === 'admin_finance' ? ' internal' : '' ?>" id="chat"
         <?php if ($isDirect): ?>data-conv="<?= (int)$direct['id'] ?>" data-note="<?= e($direct['note']) ?>"
         <?php else: ?>data-refund="<?= (int)$refund['id'] ?>"<?php endif; ?>
         data-csrf="<?= e(csrf_token()) ?>"
         data-api="<?= e(url('api')) ?>"
         data-kind="<?= e($start) ?>">
  <?php if (!$isDirect): ?>
  <header class="chat-head">
    <div class="chat-tabs" role="tablist" aria-label="Conversations">
      <?php foreach ($threads as $kind => $t): $n = (int)($unread[$kind] ?? 0); ?>
        <button type="button" role="tab" class="chat-tab<?= $kind === $start ? ' on' : '' ?>"
                aria-selected="<?= $kind === $start ? 'true' : 'false' ?>"
                data-kind="<?= e($kind) ?>" data-note="<?= e($t['note']) ?>" data-send="<?= $t['send'] ? '1' : '0' ?>">
          <?= e($t['label']) ?><span class="tab-badge"<?= $n ? '' : ' hidden' ?>><?= $n ?></span>
        </button>
      <?php endforeach; ?>
    </div>
  </header>
  <?php endif; ?>
  <p class="chat-note"></p>
  <div class="chat-list" aria-live="polite">
    <p class="chat-empty">No messages yet. Send a message, photo, video or document.</p>
  </div>
  <form class="chat-form" autocomplete="off">
    <div class="chat-previews"></div>
    <div class="chat-row">
      <label class="attach" title="Attach photos, videos or documents">
        <input type="file" multiple hidden accept="image/*,video/*,.pdf,.doc,.docx">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 11.5l-8.6 8.6a5 5 0 0 1-7.1-7.1l9-9a3.3 3.3 0 0 1 4.7 4.7l-9 9a1.7 1.7 0 0 1-2.4-2.4l8.3-8.3"/></svg>
        <span class="sr">Attach files</span>
      </label>
      <textarea rows="1" placeholder="Type a message" maxlength="4000"></textarea>
      <button type="submit" class="btn">Send</button>
    </div>
    <p class="chat-error" hidden></p>
  </form>
</section>
<script src="<?= e(url('assets/js/chat.js') . '?v=' . (int)@filemtime(__DIR__ . '/../assets/js/chat.js')) ?>"></script>
