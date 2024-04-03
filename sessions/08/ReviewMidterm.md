# Review Midterm

1. a. SELECT AVG(x) FROM Stats;
    null does not count    (1 + 2 + 3 + 4 ) / 4
b.  SELECT AVG(x) FROM Stats WHERE x > 4; null

2. CREATE TABLE Students (
   id INT PRIMARY KEY AUTO_INCREMENT, -- unique
   firstname varchar(50),
   lastname varchar(50),
   major varchar(5)
 )

 3. 
   UPDATE Students SET major='CPE' WHERE major='CE';  O(n)
    SELECT COUNT(*) from Students WHERE lastname LIKE 'B%' O(n)  TABLE SCAN
   SELECT * FROM Studnets where id = 1; O(log n)
   SELECT * FROM Students where id mod 2 != 0; // O(n)
   SELECT * FROM Students ORDER BY major, lastname; //O(n log n)
      if a.major < b.major OR a.major == b.major AND a.lastname < b.lastname
        swap(a,b)
 DELETE FROM STudents where (...) O(n)

 4. CREATE INDEX xxx on Students(major);
   select * FROM Students ORDER BY major; O(n)


1. CREATE VIEW xyz AS SELECT firstname, lastname FROM Students;

SELECT * FROM xyz;