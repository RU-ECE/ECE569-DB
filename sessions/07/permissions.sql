GRANT ALL on Users to dov;
DROP TABLE IF EXISTS Users;
CREATE TABLE Users (
  id int primary key auto_increment,
  firstname varchar(20),
  lastname varchar(20)
);

REVOKE ALL on Users FROM dov;
