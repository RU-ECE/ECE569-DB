DROP TABLE IF EXISTS Talks;
CREATE TABLE Talks (
   ID INT PRIMARY KEY,
   TITLE VARCHAR(100)
);

DROP TABLE IF EXISTS BackTalks;
CREATE TABLE BackTalks (
   ID INT PRIMARY KEY,
   TITLE VARCHAR(100)
);

INSERT INTO Talks VALUES(1, 'SQL is great');
INSERT INTO Talks VALUES(2, 'Famous Databases');
INSERT INTO Talks VALUES(3, 'For Codd and Country');
INSERT INTO Talks VALUES(4, 'My Favorite Sorting algorithms');

DROP PROCEDURE IF EXISTS sp_backup;
DELIMITER //
CREATE PROCEDURE sp_backup()
BEGIN
    DECLARE going INT DEFAULT 1;
    DECLARE myid INTEGER;
    DECLARE mytitle VARCHAR(100);
    DECLARE cur CURSOR FOR SELECT * FROM Talks;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET going = 0;
--    START TRANSACTION;
    OPEN cur;
    backup_loop:
    WHILE true DO
        FETCH cur INTO myid, mytitle;
        IF going = 0 THEN
            LEAVE backup_loop; -- Leave the loop if no more rows
        END IF;
        INSERT INTO BackTalks VALUES(myid, mytitle);
    END WHILE backup_loop;
    CLOSE cur;
--    COMMIT;
END//
DELIMITER ;
