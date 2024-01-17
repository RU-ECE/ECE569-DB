# Preparing to take ECE569 Databases

It is ideal to bring a laptop to class though it is not required. If you do not have a laptop because you cannot afford one, please contact me. 

The majority of this course is designing and implementing databases, learning how they work, and optimizing them for performance. We will cover both SQL and non-SQL databases. As part of this, you will have to write programs to write and read data to/from databases.

Accordingly, you should know how to use a programming language and
debug your code. I will probably focus on C++, but I may use some Java
examples. I am not comfortable with python, but will allow you to use
the language of your choice for those exercises.

A knowledge of basic linux will be helpful as we will be using a shared machine. Most of the time you may use the database on your own laptop.

If you use a language I don't know well (such as python), you will have to get it working on
your own.

Install the following packages on your computer

* MySQL (you can install any database using docker which is optional, see below)
* ssh (you will need to log into a shared linux server). On windows putty is one choice for an ssh tool
* git (you will have to work with at least one other person on a final project, and your project will be in a private repo on github )
* Setup a github account (You can sign up for a [student developer pack](https://education.github.com/pack) if you don't have one)
* The programming API for a language (like Connect or Connect/J for MySQL) that allows you to write programs to interact with your database.
* Microsoft vscode. I use this in class to edit programs and hope you will use it to join me (using the liveshare plugin)

The following are optional, but the more you do, the better for you

* Docker: This allows you to set up various packages in a Linux container. Using docker is a valuable skill, but is not required for this course
* PostGRES. This is an alternative to MySQL that adds an object oriented interface to the SQL relational model.
* MongoDB. We can use this online, but installing on your machine may help you
* MariaDB: this is the fully open source branch of MySQL, which was bought by Oracle
* Oracle: This is expensive and hugely complicated, but it is a commercial product that is a lot better in some ways than MySQL, PostGRES, etc.