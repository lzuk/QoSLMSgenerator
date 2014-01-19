CREATE VIEW qosdata AS

SELECT
`customers`.`id` AS customerID,
`customers`.`lastname`,
`customers`.`name`,

`nodes`.`id` AS nodeID,
`nodes`.`ipaddr`,
`tariffs`.`name` AS tariffName,
`tariffs`.`downceil`,
`tariffs`.`downceil_n`,
`tariffs`.`downrate`,
`tariffs`.`downrate_n`,
`tariffs`.`upceil`,
`tariffs`.`upceil_n`,
`tariffs`.`uprate`,
`tariffs`.`uprate_n`
                                FROM
                                        `nodes`
                                LEFT JOIN
                                        `customers`
                                ON
                                        `customers`.`id` = `nodes`.`ownerid`
                                LEFT JOIN
                                        `netdevices`
                                ON
                                        `netdevices`.`id` = `nodes`.`netdev`
                                LEFT JOIN
                                        `nodegroupassignments`
                                ON
                                        `nodegroupassignments`.`nodeid` = `nodes`.`id`
                                LEFT JOIN
                                        `nodegroups`
                                ON
                                        `nodegroups`.`id` = `nodegroupassignments`.`nodegroupid`
                                LEFT JOIN
                                        `nodeassignments`
                                ON
                                        `nodeassignments`.`nodeid` = `nodes`.`id`
                                LEFT JOIN
                                        `assignments`
                                ON
                                        `assignments`.`id` = `nodeassignments`.`assignmentid`
                                LEFT JOIN
                                        `tariffs`
                                ON
                                        `tariffs`.`id` = `assignments`.`tariffid`
                                WHERE
                                        `nodes`.`ownerid` > 0
                                ORDER BY
                                        `nodes`.`ipaddr` ASC

