# ECE569 Final Project Ideas

## Rules

Projects may be one of the topics described here (sign up) or something your team picks (in which case you must submit a one page proposal for what you will do)

Each team member is expected to contribute the project.

We will discount language skill in judging, but each team is responsible to describe what was done, and should be able to answer questions explaining the technical details of the project.

Final projects will be judged based on
  * Work checked into repository over multiple weeks (30%)
  * Results (50%)
  * Clarity of Presentation (10%)
  * Technical difficulty (10%)

In other words, if you pick a really difficult subject, you will get a high technical score, which can slightly offset the fact that you don't get it all done, but basically you are expected to pick an idea, get it done at least enough to demonstrate the concept, and present clearly what you did and what each team member contributed to the project.

## Topics

The following are some possible topics. Only one team may attempt each topic in a given technology, but two teams may attempt the same topic with different technologies and compare results. For example, the gapminder database could be implemented in HFS, MongoDB, and MySQL by three different groups, and the groups could compare results in terms of performance and any problems. Groups may coordinate efforts in such cases.

1. Learning Management System (LMS)
   1. Implement the database for a learning management system such as Canvas for a school like Rutgers
   2. Demonstrate with a subset of the courses, for example some of the courses for ECE.
   3. Creativity in acquiring data such as scraping it from existing course catalogs and systems would be impressive. No one expects you to manually load dozens of courses, but if you could scrape it using an existing file, or a web site, that would be good.
2. Re-implement part of Kevin Wine's departmental system. This will require you to talk to Kevin (the customer) and be sure you understand exactly what the department wants. In order to make sure you are respectful of his time, you will first meet with me and make sure you have all your information as complete as possible before requesting an interview.
3. Extend the nutrition project we analyzed for homework to build a database and application that support recipes. You should be able to
   * Create a recipe
   * Delete a recipe
   * Add a new food type
   * Add a new nutrient
     * The FDA database contains the core nutrients understood to be fairly important to life.
     * Other nutrients may be important, but may be rare (not in most foods)
     * Poisons should be noted, such as [cyanide in flax seed](https://nutritionfacts.org/video/friday-favorites-how-well-does-cooking-destroy-the-cyanide-in-flax-seeds-and-should-we-be-concerned-about-it/), or the [neurotoxin in star fruit](https://nutritionfacts.org/blog/the-neurotoxin-in-star-fruit/) 
     * Cautions should be issued whenever an ingredient with a very high concentration of something that could cause trouble for a class of people, for example star fruit for people with kidney disease, and almonds or spinach for people who make calcium-oxalate kidney stones.
     * Because so much nutrition noise is out there, you should contain in your database citations to the original sources defining the information. So for example if you are going to warn that too much iron can kill (true) and give an amount you should be able to point to the source of the information.
   * View the total nutrients in a recipe in absolute terms
   * Specify a diet for a particular class of person (for example a pregnant woman) to determine whether all nutritional needs are properly satisfied
   * Perhaps suggest substitutions for ingredients for certain classes of people.
   * Building a web site is obviously beyond the requirements, but any visual display will be spectacular and good for extra credit. The most important thing, however, is that your data be correct.
4. Gapminder Database.
   * Gapminder was an amazing project by the late Dr. Hans Rosling.
   * See his ]TED talk](https://www.youtube.com/watch?v=hVimVzgtD6w&t=306s).
   * The data for the project is [located here](https://open-numbers.github.io/).
   * Here is [a description of their data definition format](https://open-numbers.github.io/ddf.html) The intention of this project is to implement a much more compact dataset in HDF and have a single file with all the data. The problem is that many of their datasets have errors (or gaps in data), so you will have to clean up as you copy over. For example, there may be missing years of data. Compare the memory size and speed of the original CSV-based dataset to your implementation in HDF5.
5. Impact of Binary Data Transfer on SQL Performance
   * Build Mysql or Mariadb from source code and determine how much time would be saved if data were transmitted in binary rather than ASCII.
   * This is a tough project requiring a high degree of C/C++ programming skill.
6. Build a blockchain ledger.
   * Build a distributed ledger designed to be immutable using the crypto-primitives we discuss in class, but a cooperative oligopoly rather than public repo. Just provide the functionality to verify the books using the secure hashes.
   * Multiple parties can audit the blockchain
   * An oligopoly of central trusted parties replicate the ledger
   * Anyone can obtain a verifiable signed proof that a transaction has happened, and that as of a particular date, that the record is known. No one can come in and change that without it being known after the fact. 