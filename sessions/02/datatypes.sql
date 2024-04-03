DROP TABLE IF EXISTS signedinttypes;

CREATE TABLE signedinttypes (
  a int, // -2.1billion to 2.1 billion
  b bigint, // -2^63 .. 2^63
  c smallint,  // -32768 .. 32767
  d tinyint    // -128 .. 127
);

INSERT INTO signedinttypes VALUES(1, 2, 3, 4);
INSERT INTO signedinttypes VALUES(-2147483648, -9223372036854775808, -32768, -128);
INSERT INTO signedinttypes VALUES(2147483647, 9223372036854775807, 32767, 127);
INSERT INTO signedinttypes VALUES(null, null, null, null);


DROP TABLE IF EXISTS unsignedinttypes;
CREATE TABLE unsignedinttypes (
  a int unsigned, // 0 .. 4.2billion
  b bigint unsigned,  // 0 .. 2^64-1
  c smallint unsigned, // 0 .. 65535
  d tinyint unsigned   // 0 .. 255
);

INSERT INTO unsignedinttypes VALUES(0, 1, 2, 3);
INSERT INTO unsignedinttypes VALUES(4294967295, 18446744073709551615, 65535, 255);
INSERT INTO unsignedinttypes VALUES(null, null, null, null);

DROP TABLE IF EXISTS floattypes;
CREATE TABLE floattypes (
  a float, // 32-bit
  b real   // 64-bits
  // c double // 64-bits
);
INSERT INTO floattypes VALUES(1.2345678901234567, 1.2345678);
INSERT INTO floattypes VALUES(1.23456789012345e+3, 1.2345678e+3);
INSERT INTO floattypes VALUES(1.23456789012345e+300, 1.2345678e+38);
INSERT INTO floattypes VALUES(-1.23456789012345e+300, -1.2345678e+38);
/*
These floating point values are not supported by mysql because of their parsing
INSERT INTO floattypes VALUES(INF, INF);
INSERT INTO floattypes VALUES(-INF, -INF);
INSERT INTO floattypes VALUES(NaN, NaN);
*/
INSERT INTO floattypes VALUES(null, null);

DROP TABLE IF EXISTS stringtypes;
CREATE TABLE stringtypes (
  a char(3),
  b varchar(20),
  c nchar(8),
  d nvarchar(255)
);
INSERT INTO stringtypes VALUES('abc', 'Simplified Chinese', '汉字', '我听到了，但又忘记了。 我看到并且我记得。 我知道并且我理解。');
INSERT INTO stringtypes VALUES('abc', 'Traditional Chinese', '漢字', '我聽到了，但又忘記了。 我看到並且我記得。 我知道並且我理解。');
INSERT INTO stringtypes VALUES('abc', 'Japanese Kanjii', '漢字', 'あなたのお母さんはハムスターです');
INSERT INTO stringtypes VALUES('abc', 'Korean', '한국인', '보기 좋은 떡이 먹기도 좋다');
INSERT INTO stringtypes VALUES('abc', 'Greek', 'Ελληνικά', 'Η επιτυχία εξαρτάται από την προσπάθεια.');

DROP TABLE IF EXISTS datetypes;
CREATE TABLE datetypes (
  a date,
  b time,
  c datetime, // 1000 - 9999 Y10k issue
  d timestamp // 1970 - 2038..
);

insert into datetypes VALUES('2024-02-05', '12:30:30', '2023-01-01 11:59:59', '1970-01-01 00:00:01');
insert into datetypes VALUES('2024-02-05', '12:30:30', '2023-01-01 11:59:59', '1970-01-01 00:00:01.00001');
insert into datetypes VALUES('2024-02-05', '12:30:30', '2023-01-01 11:59:59', '1970-01-01 00:00:01.000005');
insert into datetypes VALUES('2024-02-05', '12:30:30', '2023-01-01 11:59:59', '2038-01-18 00:00:00');



select * from signedinttypes;
select * from unsignedinttypes;
select * from floattypes;
select * from stringtypes;
select * from datetypes;
