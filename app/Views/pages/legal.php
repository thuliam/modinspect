<?php /** LS-90 T5: one published legal document as plain text. Inputs: $document (title, version, body_text, published_at). */ ?>
<section class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <h1 class="h3"><?= htmlspecialchars((string) $document['title']) ?></h1>
            <p class="text-muted small">ฉบับที่ <?= htmlspecialchars((string) $document['version']) ?> · เผยแพร่ <?= htmlspecialchars(substr((string) $document['published_at'], 0, 10)) ?></p>
            <div class="card"><div class="card-body" style="white-space: pre-line"><?= htmlspecialchars(str_replace("\r\n", "\n", (string) $document['body_text'])) ?></div></div>
        </div>
    </div>
</section>
