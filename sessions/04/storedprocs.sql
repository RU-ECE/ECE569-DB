DROP PROCEDURE IF EXISTS sp_MakeAccounts;

DELIMITER //
CREATE PROCEDURE sp_MakeAccounts(IN num_accounts int)
BEGIN
  DECLARE i int DEFAULT 0;
  START TRANSACTION;
  REPEAT
    SET i = i + 1;
    INSERT INTO Accounts(primary_owner, secondary_owner, balance)
      VALUES(1, null, 1.0);
  UNTIL i >= num_accounts
  END REPEAT;
  COMMIT;
END
//
DELIMITER ;

DROP PROCEDURE IF EXISTS sp_GiveMoney;

DELIMITER //
CREATE PROCEDURE sp_GiveMoney(IN amt bigint unsigned, IN userid int)
BEGIN
  UPDATE Accounts SET balance = balance + amt WHERE primary_owner = userid; 
END
//
DELIMITER ;
