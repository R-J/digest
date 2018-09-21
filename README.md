# digest
Plugin for Vanilla Forums which allows to send periodically digests to forum users (WIP)

This plugin needs my simplequeue plugin which itself needs to be set up with a cron job calling its endpoint. That plugin (simplequeue) hasn't been tested by itself. So this construct is really experimental by now.

What is missing:
- setting page which allows to set up the initial task
- mail sending process (by now only debug messages are printed)
- data gathering for filling a digest
- a mail template for the digest
- a way to make that digest customizable, should be done in the settings and not by adding a file in the folder
- documentation
-testings...
