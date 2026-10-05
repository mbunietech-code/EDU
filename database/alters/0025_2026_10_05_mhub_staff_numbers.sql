-- Switch auto-generated staff numbers from STF-00001 to Mhub-001 (staff records and payroll snapshots).
UPDATE `finance_staff` SET `staff_number` = CONCAT('Mhub-', LPAD(CAST(SUBSTRING(`staff_number`, 5) AS UNSIGNED), 3, '0')) WHERE `staff_number` REGEXP '^STF-[0-9]+$';
UPDATE `finance_payroll_items` SET `staff_number` = CONCAT('Mhub-', LPAD(CAST(SUBSTRING(`staff_number`, 5) AS UNSIGNED), 3, '0')) WHERE `staff_number` REGEXP '^STF-[0-9]+$';
