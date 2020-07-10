.. include:: ../Includes.txt

.. _introduction:

============
Introduction
============

.. tip::

   New to reStructuredText and Sphinx?

   Get an introduction:
   https://docs.typo3.org/m/typo3/docs-how-to-document/master/en-us/WritingReST/Index.html

   Use this cheat sheet as reference:
   https://docs.typo3.org/m/typo3/docs-how-to-document/master/en-us/WritingReST/CheatSheet.html

.. _what-it-does:

What does it do?
================

The aim of this chapter is to provide a general overview of your extension.

* What does it do?
* What problems does it solve?
* Who is the target audience?

This chapter should provide information that will help inform 
potential users and assist them in deciding if they should 
install and use this extension.

.. important::

   Don't forget to set extension's version number in :file:`Settings.cfg` file,
   in the :code:`release` property.
   It will be automatically picked up on the cover page by the :code:`|release|` substitution.

.. _screenshots:

Screenshots
===========

This chapter should help people understand how the extension works.
Remove it if it is not relevant.

.. figure:: ../Images/IntroductionPackage.png
   :class: with-shadow
   :alt: Introduction Package
   :width: 300px

   Introduction Package after installation (caption of the image).

How the Frontend of the Introduction Package looks like after installation (legend of the image).
