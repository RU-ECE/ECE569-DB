DROP TABLE IF EXISTS Accounts;
CREATE TABLE Accounts (
  id int primary key auto_increment,
  balance double
);

INSERT INTO Accounts(balance) VALUES(100.0);
INSERT INTO Accounts(balance) VALUES(1000.0);

CREATE TABLE Transactions (
  id int primary key auto_increment,
  t  datetime,
  fromid int,
  toid int,
  foreign key (fromid) references Accounts(id),
  foreign key (toid) references Accounts(id),
  amt double
);


DROP PROCEDURE IF EXISTS sp_transfer;
DELIMITER //
CREATE PROCEDURE sp_transfer(IN fromid int, IN toid int, IN amt double)
BEGIN
   DECLARE frombal double;
   START TRANSACTION;
   SELECT balance FROM Accounts WHERE id = fromid INTO @frombal;
    IF frombal < amt THEN
      select "FAILURE";
      ROLLBACK;
    ELSE
        UPDATE Accounts SET balance = balance - amt WHERE id = fromid;
        UPDATE Accounts SET balance = balance + amt WHERE id = toid;
        INSERT INTO Transactions(t,fromid,toid,amt) VALUES (NOW(), fromid, toid, amt);
        COMMIT;
    END IF;

END//
DELIMITER ;

CALL sp_transfer(1, 2, 100.0);
CALL sp_transfer(1, 2, 100.0); -- 2nd should fail beacuse no Money
CALL sp_transfer(2, 3, 100.0);
CALL sp_transfer(2, 1, -10000000000.0); -- this should fail because it's evil

select * from Accounts;