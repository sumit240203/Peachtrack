-- Add audit fields so employees can see that an Admin edited a shift (and who did it)
-- Run this in phpMyAdmin on the peachtrack database.

ALTER TABLE shift
  ADD COLUMN Updated_At DATETIME NULL,
  ADD COLUMN Updated_By INT NULL,
  ADD COLUMN Update_Note VARCHAR(255) NULL;

-- Optional: foreign key (only if you want enforcement; comment out if it causes issues)
-- ALTER TABLE shift
--   ADD CONSTRAINT fk_shift_updated_by FOREIGN KEY (Updated_By) REFERENCES employee(Employee_ID);
