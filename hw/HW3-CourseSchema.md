# Design a Course Schema

A schema defines how data is organized within a relational database.
It is the design of a set of tables to achieve a particular purpose.

In this homework, you are to define the schema for a set of courses at Rutgers.
We will just worry about ECE, not the whole school.

You will need to define:

1. Students
   * NetID
   * RUID
   * firstname
   * lastname
   * start date
   * graduation date (may be unspecified if they have not graduated yet)
      * Problem: Student may be at Rutgers for undergrad, masters and PhD.
      * Most students are only here for one or two degrees.
      * Make this work!
   * Courses the student has taken (must include none, or could be many)
2. Courses
  * course id (16:332:569 is the course id for this course)
  * course title (ie Database Systems)
  * description: longer text with a description
  * prerequisites: any courses you must take before this one (often, none)

3. A course is a specific kind of class, but it may be taught multiple times
  * semester
  * professor teaching the course
  * students taking the course
  * room number
  * max people allowed in the class (for now you put 40 in this field, obviously this depends on the room in general)
  
3. Professors also have NetID, RUID, firstname, lastname, start date, end date

Create your tables in 3NF. The clearest way to state this:
All fields in each table should be a function of the key, and only the key.

In your script, insert hardcoded courses for testing including:

* ECE569 (this course)
* ECE573 (data structures)
* ECE451/566 (parallel and distributed, same course, but two numbers (one for undergrad, other is grad!)
* two more courses of your choosing that you have taken or plan to take
* With you and your partner, insert yourselves and two other student as examples (total 4 students)
* Write the following stored procedures
  * enroll (to take a class)
    * Add a student into a class and increase enrollment by 1
    * If the class is full, it has to fail. We won't implement a waiting list
      * Use a transaction
  * withdraw (remove student from a class)
  * display all students in a class
  * display all students who have ever taken a given course
  * display how many students have taken each course

Build sufficient data and demonstrate calling each of your stored procedures. You do not have to do this on the shared server.

Assignment submission is the sql file containing all the code to load this into a database, and output showing it run.
