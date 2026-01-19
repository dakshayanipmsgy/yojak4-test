<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../app/bootstrap.php';

safe_page(function () {
    require_superadmin_or_permission('staff_guide_editor');

    $title = get_app_config()['appName'] . ' | New Staff Guide Section';

    render_layout($title, function () {
        ?>
        <style>
            .editor {
                display: grid;
                gap: 16px;
            }
            .field {
                display: flex;
                flex-direction: column;
                gap: 6px;
                margin-bottom: 12px;
            }
            .field input,
            .field textarea,
            .field select {
                padding: 10px 12px;
                border-radius: 10px;
                border: 1px solid var(--border);
            }
            textarea { min-height: 90px; }
            .block {
                border: 1px solid var(--border);
                border-radius: 12px;
                padding: 14px;
                background: #fff;
                display: grid;
                gap: 12px;
            }
            .block-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            .block-actions {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }
            .muted { color: var(--muted); }
        </style>

        <div class="card">
            <h2 style="margin:0;">New Staff Guide Section</h2>
            <p class="muted" style="margin:6px 0 0;">Create a staff guide section using structured content blocks.</p>
        </div>

        <form method="post" action="/superadmin/staff_guide_manage/create.php" class="editor" id="guide-form">
            <input type="hidden" name="csrf_token" value="<?= sanitize(csrf_token()); ?>">
            <div class="card">
                <div class="field">
                    <label>Title</label>
                    <input name="title" required placeholder="Guide section title">
                </div>
                <div class="field">
                    <label>Summary</label>
                    <textarea name="summary" placeholder="Short summary"></textarea>
                </div>
                <div class="field">
                    <label>Audience</label>
                    <select name="audience">
                        <option value="staff">Staff</option>
                    </select>
                </div>
                <label style="display:flex; gap:8px; align-items:center;">
                    <input type="checkbox" name="published" value="1" checked>
                    Publish immediately
                </label>
            </div>

            <div class="card">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div>
                        <h3 style="margin:0;">Content Blocks</h3>
                        <p class="muted" style="margin:6px 0 0;">Add blocks and reorder using arrows.</p>
                    </div>
                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <select id="block-type">
                            <option value="intro">Intro</option>
                            <option value="steps">Steps</option>
                            <option value="tips">Tips</option>
                            <option value="faq">FAQ</option>
                            <option value="warning">Warning</option>
                            <option value="do_dont">Do/Don’t</option>
                        </select>
                        <button class="btn secondary" type="button" id="add-block">Add Block</button>
                    </div>
                </div>

                <div id="blocks" style="margin-top:12px; display:grid; gap:12px;"></div>
            </div>

            <div style="display:flex; justify-content:flex-end;">
                <button class="btn" type="submit">Create Section</button>
            </div>
        </form>

        <template id="template-intro">
            <div class="block" data-block>
                <div class="block-header">
                    <strong>Intro</strong>
                    <div class="block-actions">
                        <button type="button" class="btn secondary" data-move="up">↑</button>
                        <button type="button" class="btn secondary" data-move="down">↓</button>
                        <button type="button" class="btn danger" data-remove>Remove</button>
                    </div>
                </div>
                <input type="hidden" name="blocks[__INDEX__][type]" value="intro">
                <div class="field">
                    <label>Intro text</label>
                    <textarea name="blocks[__INDEX__][text]"></textarea>
                </div>
            </div>
        </template>

        <template id="template-warning">
            <div class="block" data-block>
                <div class="block-header">
                    <strong>Warning</strong>
                    <div class="block-actions">
                        <button type="button" class="btn secondary" data-move="up">↑</button>
                        <button type="button" class="btn secondary" data-move="down">↓</button>
                        <button type="button" class="btn danger" data-remove>Remove</button>
                    </div>
                </div>
                <input type="hidden" name="blocks[__INDEX__][type]" value="warning">
                <div class="field">
                    <label>Title (optional)</label>
                    <input name="blocks[__INDEX__][title]">
                </div>
                <div class="field">
                    <label>Warning text</label>
                    <textarea name="blocks[__INDEX__][text]"></textarea>
                </div>
            </div>
        </template>

        <template id="template-steps">
            <div class="block" data-block>
                <div class="block-header">
                    <strong>Steps</strong>
                    <div class="block-actions">
                        <button type="button" class="btn secondary" data-move="up">↑</button>
                        <button type="button" class="btn secondary" data-move="down">↓</button>
                        <button type="button" class="btn danger" data-remove>Remove</button>
                    </div>
                </div>
                <input type="hidden" name="blocks[__INDEX__][type]" value="steps">
                <div class="field">
                    <label>Block title</label>
                    <input name="blocks[__INDEX__][title]" placeholder="Workflow steps">
                </div>
                <div class="field">
                    <label>Steps (one per line)</label>
                    <textarea name="blocks[__INDEX__][items_text]"></textarea>
                </div>
            </div>
        </template>

        <template id="template-tips">
            <div class="block" data-block>
                <div class="block-header">
                    <strong>Tips</strong>
                    <div class="block-actions">
                        <button type="button" class="btn secondary" data-move="up">↑</button>
                        <button type="button" class="btn secondary" data-move="down">↓</button>
                        <button type="button" class="btn danger" data-remove>Remove</button>
                    </div>
                </div>
                <input type="hidden" name="blocks[__INDEX__][type]" value="tips">
                <div class="field">
                    <label>Block title</label>
                    <input name="blocks[__INDEX__][title]" placeholder="Tips">
                </div>
                <div class="field">
                    <label>Tips (one per line)</label>
                    <textarea name="blocks[__INDEX__][items_text]"></textarea>
                </div>
            </div>
        </template>

        <template id="template-faq">
            <div class="block" data-block>
                <div class="block-header">
                    <strong>FAQ</strong>
                    <div class="block-actions">
                        <button type="button" class="btn secondary" data-move="up">↑</button>
                        <button type="button" class="btn secondary" data-move="down">↓</button>
                        <button type="button" class="btn danger" data-remove>Remove</button>
                    </div>
                </div>
                <input type="hidden" name="blocks[__INDEX__][type]" value="faq">
                <div class="field">
                    <label>Block title</label>
                    <input name="blocks[__INDEX__][title]" placeholder="FAQ">
                </div>
                <div class="field">
                    <label>Questions (one per line)</label>
                    <textarea name="blocks[__INDEX__][faq_questions]"></textarea>
                </div>
                <div class="field">
                    <label>Answers (one per line)</label>
                    <textarea name="blocks[__INDEX__][faq_answers]"></textarea>
                </div>
            </div>
        </template>

        <template id="template-do_dont">
            <div class="block" data-block>
                <div class="block-header">
                    <strong>Do / Don’t</strong>
                    <div class="block-actions">
                        <button type="button" class="btn secondary" data-move="up">↑</button>
                        <button type="button" class="btn secondary" data-move="down">↓</button>
                        <button type="button" class="btn danger" data-remove>Remove</button>
                    </div>
                </div>
                <input type="hidden" name="blocks[__INDEX__][type]" value="do_dont">
                <div class="field">
                    <label>Block title</label>
                    <input name="blocks[__INDEX__][title]" placeholder="What to do / not do">
                </div>
                <div class="field">
                    <label>Do items (one per line)</label>
                    <textarea name="blocks[__INDEX__][do_text]"></textarea>
                </div>
                <div class="field">
                    <label>Don’t items (one per line)</label>
                    <textarea name="blocks[__INDEX__][dont_text]"></textarea>
                </div>
            </div>
        </template>

        <script>
            (() => {
                const blocksContainer = document.getElementById('blocks');
                const addBtn = document.getElementById('add-block');
                const typeSelect = document.getElementById('block-type');

                const templateFor = (type) => document.getElementById(`template-${type}`);

                const renumberBlocks = () => {
                    const blocks = blocksContainer.querySelectorAll('[data-block]');
                    blocks.forEach((block, index) => {
                        block.querySelectorAll('input, textarea').forEach((field) => {
                            if (field.name) {
                                field.name = field.name.replace(/blocks\[\d+\]/g, `blocks[${index}]`);
                            }
                        });
                    });
                };

                const wireBlock = (block) => {
                    block.querySelectorAll('[data-move]').forEach((btn) => {
                        btn.addEventListener('click', () => {
                            const direction = btn.dataset.move;
                            if (direction === 'up') {
                                const prev = block.previousElementSibling;
                                if (prev) {
                                    blocksContainer.insertBefore(block, prev);
                                }
                            } else {
                                const next = block.nextElementSibling;
                                if (next) {
                                    blocksContainer.insertBefore(next, block);
                                }
                            }
                            renumberBlocks();
                        });
                    });
                    const removeBtn = block.querySelector('[data-remove]');
                    if (removeBtn) {
                        removeBtn.addEventListener('click', () => {
                            block.remove();
                            renumberBlocks();
                        });
                    }
                };

                const addBlock = () => {
                    const type = typeSelect.value;
                    const template = templateFor(type);
                    if (!template) return;
                    const index = blocksContainer.querySelectorAll('[data-block]').length;
                    const html = template.innerHTML.replace(/__INDEX__/g, index.toString());
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = html.trim();
                    const block = wrapper.firstElementChild;
                    if (block) {
                        blocksContainer.appendChild(block);
                        wireBlock(block);
                    }
                };

                if (addBtn) {
                    addBtn.addEventListener('click', addBlock);
                }
            })();
        </script>
        <?php
    });
});
