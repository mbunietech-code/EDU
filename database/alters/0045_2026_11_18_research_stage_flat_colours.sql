-- Research work requests: no violet / pink "AI look" stage colours. Draft ready becomes teal;
-- any stage an admin set to violet or pink becomes indigo. Idempotent (guarded by WHERE).

UPDATE `research_request_stages` SET `color` = 'teal' WHERE `key` = 'draft_ready' AND `color` = 'violet';
UPDATE `research_request_stages` SET `color` = 'indigo' WHERE `color` IN ('violet', 'pink');
