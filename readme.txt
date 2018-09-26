This serverside program is designed to act as a billing interface between an existing MySQL DB and several android clients
An admin requires the steps to make this program run as wanted:
1) Update the .conf files and configure your MySQL server accordingly to the given settings. The user needs to have full permission on the given DB. no malicious interaction is possible as the php side acts as a limited interface only providing certain capabilities to the user. Additionally when required prepared statements are used.
2) Start the php program on a free shell and observe the output. If it ends up in some listening state everything went fine.
3) Start the cronjob. It is designed to run 5 minutes after midinight at the 1st of each month to calculate the costs for each employee
