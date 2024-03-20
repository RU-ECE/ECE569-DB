DROP TABLE IF EXISTS EnumTest;
CREATE TABLE EnumTest (
  amt double,
  unit ENUM('ug', 'mg', 'g', 'kg', 'kcal')
);

INSERT INTO EnumTest VALUES(1.5, 'ug');
INSERT INTO EnumTest VALUES(2.5, 'mg');
INSERT INTO EnumTest VALUES(4.0, 'kg');

SELECT amt, unit+0 FROM EnumTest;

ALTER TABLE EnumTest MODIFY unit 
  ENUM('ng', 'ug', 'mg', 'g', 'kg', 'kcal');

INSERT INTO EnumTest VALUES(9.2, 'ng');
SELECT * FROM EnumTest;
ALTER TABLE EnumTest MODIFY unit
  ENUM('ug', 'mg', 'g', 'kg', 'kcal');
SELECT amt, unit+0 FROM EnumTest;
