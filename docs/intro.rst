.. index::
   single: Introduction
   
Introduction
============

Good developers always version their code using a SCM system, so why don't they
do the same for their database schema?

Phinx allows developers to alter and manipulate databases in a clear and
concise way. It avoids the use of writing SQL by hand and instead offers a
powerful API for creating migrations using PHP code. Developers can then
version these migrations using their preferred SCM system. This makes Phinx
migrations portable between different database systems. Phinx keeps track of
which migrations have been run so you can worry less about the state of your
database and instead focus on building better software.