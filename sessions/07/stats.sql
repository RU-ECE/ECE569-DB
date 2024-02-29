DROP TABLE IF EXISTS stats;
CREATE TABLE stats (
    x double
);

INSERT INTO stats VALUES(1.0);
INSERT INTO stats VALUES(9.0);
INSERT INTO stats VALUES(2.0);
INSERT INTO stats VALUES(8.0);
INSERT INTO stats VALUES(null); -- null is ignored in aggregate functions

// if no values are defined, then avg(x) = null
select avg(x) FROM stats;

