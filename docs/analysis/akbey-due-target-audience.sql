-- READ ONLY. Prepared only; do not run without approval for the intended environment.
-- Requires the new dues.target_audience column to exist.
-- Bind :apartment_id to the verified AKBEY apartment ID; never select by name alone.
-- Includes soft-deleted dues. No responsibility is inferred from batch/account/category.
SELECT COUNT(*) AS total_dues,
       COALESCE(SUM(CASE WHEN target_audience = 'tenant_priority' THEN 1 ELSE 0 END), 0) AS tenant_priority_count,
       COALESCE(SUM(CASE WHEN target_audience = 'owner_only' THEN 1 ELSE 0 END), 0) AS owner_only_count,
       COALESCE(SUM(CASE WHEN target_audience IS NULL THEN 1 ELSE 0 END), 0) AS unknown_count
FROM dues
WHERE apartment_id = :apartment_id;

-- Null is not synonymous with old: new individual/imported dues are also null.
-- Classify only the recorded creation source, never the targeting responsibility.
SELECT CASE
           WHEN d.is_imported = 1 THEN 'imported'
           WHEN d.due_batch_id IS NULL THEN 'no_batch_unknown_source'
           WHEN b.id IS NULL THEN 'missing_batch'
           WHEN b.due_plan_id IS NOT NULL THEN 'aidat_plan'
           WHEN b.assessment_plan_id IS NOT NULL THEN 'assessment_plan'
           WHEN b.source_type = 'individual' THEN 'individual'
           ELSE COALESCE(b.source_type, 'batch_unknown_source')
       END AS recorded_source,
       COUNT(*) AS null_count
FROM dues d
LEFT JOIN due_batches b ON b.id = d.due_batch_id AND b.apartment_id = d.apartment_id
WHERE d.apartment_id = :apartment_id AND d.target_audience IS NULL
GROUP BY recorded_source
ORDER BY recorded_source;

-- Bind :rule_capture_started_at to the actual, verified deployment cutover timestamp
-- in the database timezone (not today's date or the migration filename).
-- If no verified cutover exists, do not label any rows as old.
SELECT COUNT(*) AS old_dues,
       COALESCE(SUM(CASE WHEN due_batch_id IS NOT NULL THEN 1 ELSE 0 END), 0) AS old_with_batch,
       COALESCE(SUM(CASE WHEN due_batch_id IS NULL THEN 1 ELSE 0 END), 0) AS old_without_batch
FROM dues
WHERE apartment_id = :apartment_id AND created_at < :rule_capture_started_at;

-- Missing creation timestamps cannot be assigned to either side of the cutover.
SELECT COUNT(*) AS unknown_creation_time
FROM dues
WHERE apartment_id = :apartment_id AND created_at IS NULL;
