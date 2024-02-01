#! /usr/bin/perl

use perl;

my @netids = <>;
foreach my $netid (@netids) {
  print "GRANT ALL PRIVILEGES ON ECE569.* TO $netid@localhost;";
}

