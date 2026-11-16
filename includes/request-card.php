                <?php
                $currentStatus = $request['status'];
                $nextStatus = REQUEST_NEXT_STATUS[$currentStatus] ?? null;
                $canDecline = $nextStatus !== null || $currentStatus === 'Ready for Pickup';
                $reference = $request['reference_no'];
                $headingId = 'request-' . $reference;
                ?>
                <article class="dc-card dc-request-card" aria-labelledby="<?= escape($headingId) ?>">
                    <header>
                        <div>
                            <?php if ($staff): ?>
                                <h2 id="<?= escape($headingId) ?>"><?= escape($request['type_name']) ?></h2>
                            <?php else: ?>
                                <h3 id="<?= escape($headingId) ?>"><?= escape($request['type_name']) ?></h3>
                            <?php endif; ?>
                            <p class="dc-request-meta"><?= escape($reference) ?> · <?= escape($request['requested_at']) ?></p>
                        </div>
                        <span class="dc-request-status"><?= escape($currentStatus) ?></span>
                    </header>

                    <?php if ($staff): ?>
                        <p><strong>Student</strong> <?= escape($request['student_name']) ?> (<?= escape($request['identifier_number']) ?>)</p>
                    <?php endif; ?>
                    <p><strong>Purpose</strong> <?= escape($request['purpose']) ?></p>
                    <?php if ($request['academic_year'] !== null): ?>
                        <p><strong>Academic year</strong> <?= escape($request['academic_year']) ?></p>
                    <?php endif; ?>
                    <?php if ($request['academic_term'] !== null): ?>
                        <p><strong>Academic term</strong> <?= escape($request['academic_term']) ?></p>
                    <?php endif; ?>
                    <?php if ($request['student_note'] !== null): ?>
                        <p><strong>Student note</strong> <?= escape($request['student_note']) ?></p>
                    <?php endif; ?>
                    <?php if ($request['status_reason'] !== null): ?>
                        <p><strong>Reason</strong> <?= escape($request['status_reason']) ?></p>
                    <?php endif; ?>
                    <?php if ($request['cancelled_by_role'] !== null): ?>
                        <p><strong>Cancelled by</strong> <?= escape($request['cancelled_by_role']) ?></p>
                    <?php endif; ?>

                    <?php if ($staff && $canDecline): ?>
                        <?php $isPending = $currentStatus === 'Pending Review'; ?>
                        <form class="dc-account-form" method="post" action="requests.php#request-feedback">
                            <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">
                            <input type="hidden" name="reference" value="<?= escape($reference) ?>">
                            <input type="hidden" name="expected_status" value="<?= escape($currentStatus) ?>">
                            <label for="<?= escape($headingId) ?>-reason">Reason for <?= $isPending ? 'rejection' : 'cancellation' ?></label>
                            <textarea id="<?= escape($headingId) ?>-reason" name="reason" rows="2" maxlength="1000"></textarea>
                            <div class="dc-request-actions">
                                <?php if ($nextStatus !== null): ?>
                                    <button type="submit" name="action" value="advance"><?= escape(match ($nextStatus) {
                                        'Approved' => 'Approve Request',
                                        'Processing' => 'Start Processing',
                                        'Ready for Pickup' => 'Mark Ready for Pickup',
                                    }) ?></button>
                                <?php endif; ?>
                                <button class="dc-secondary-button" type="submit" name="action" value="decline">
                                    <?= $isPending ? 'Reject Request' : 'Cancel Request' ?>
                                </button>
                            </div>
                        </form>
                    <?php elseif (!$staff && $currentStatus === 'Pending Review'): ?>
                        <form class="dc-account-form" method="post" action="requests.php#request-feedback">
                            <input type="hidden" name="csrf_token" value="<?= escape(csrfToken()) ?>">
                            <input type="hidden" name="reference" value="<?= escape($reference) ?>">
                            <button class="dc-secondary-button" type="submit" name="action" value="cancel">Cancel Request</button>
                        </form>
                    <?php endif; ?>
                </article>
