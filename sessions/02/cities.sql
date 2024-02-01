drop table if exists cities;
create table cities (
  name       varchar(20),
  country    char(3),
  primary key(country,name),
  population int
);

drop index if exists cities_by_pop;
create index cities_by_pop on cities(population);

insert into cities values ('New Brunswick', 'USA', 55000);
insert into cities values ('Shanghai', 'CHN', 26000000);
insert into cities values ('Mumbai', 'IND', 21000000);
