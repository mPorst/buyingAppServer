This serverside program is designed to act as a billing interface between an existing MySQL DB and several android clients
An admin requires to perform the following steps:
1) Update the .conf files and configure your MySQL server accordingly to the given settings. The MySQL user needs to have full permission on the given DB. no malicious interaction is possible as the php side acts as a limited interface only providing certain capabilities to the user. Additionally - when required - prepared statements are used.
2) Start the php program in the terminal and observe the output. If it ends up in listening state everything went fine.
3) Fix the paths given in the cronjob and then start it. It is designed to run 5 minutes after midinight at the 1st of each month to calculate the costs for each employee.
