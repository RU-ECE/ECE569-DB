DROP TABLE IF EXISTS Accounts;
DROP TABLE IF EXISTS Clients;

CREATE TABLE Clients (
    id  int primary key auto_increment,
    firstname varchar(20),
    lastname varchar(20)
);


CREATE TABLE Accounts(
    id  int primary key auto_increment,
    primary_owner int NOT NULL,
    foreign key (primary_owner) references Clients(id),
    secondary_owner int,
    foreign key (secondary_owner) references Clients(id),
    balance bigint unsigned
);

INSERT INTO Clients(firstname,lastname) VALUES('Yingying', 'Chen');
INSERT INTO Clients(firstname,lastname) VALUES('Dov', 'Kruger');
INSERT INTO Clients(firstname,lastname) VALUES('Shreya', 'Kumbam');

INSERT INTO Accounts(primary_owner, secondary_owner, balance) VALUES(2, null, 1000000);
INSERT INTO Accounts(primary_owner, secondary_owner, balance) VALUES(3, null, 3000);
INSERT INTO Accounts(primary_owner, secondary_owner, balance) VALUES(1, null, 20000000);

DROP PROCEDURE IF EXISTS sp_Transfer;

DELIMITER //
CREATE PROCEDURE sp_Transfer(
    IN from_account int,
    IN to_account   int,
    IN amt          bigint unsigned)
BEGIN
  START TRANSACTION;
    UPDATE Accounts SET balance = balance - amt WHERE
     id = from_account;
    UPDATE Accounts SET balance = balance + amt WHERE
     id = to_account;
  COMMIT;
END //

DELIMITER ;