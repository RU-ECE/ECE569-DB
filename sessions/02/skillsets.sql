DROP TABLE IF EXISTS Users;
CREATE TABLE Users (
  id        int auto_increment primary key,
  firstname nvarchar(50),
  lastname  nvarchar(50)
);

DROP TABLE IF EXISTS Skillnames;
CREATE TABLE Skillnames (
  skill    varchar(20) primary key
);

DROP TABLE IF EXISTS Skills;
CREATE TABLE Skills (
  userid    int,
  FOREIGN KEY (userid) REFERENCES Users(id),
  skillname      varchar(20),
  skilllevel int(2)
);
