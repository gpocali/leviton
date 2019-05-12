# leviton
Docker to turn on MyLeviton switches at night and off during the day at a user defined brightness

This docker is used to control MyLeviton switches and turn lights on at sunset and off at sunrise at a user defined brightness. This will control all switches on the account and will set them to the same defined brightness. The sunset and sunrise times are based on the location specified in the MyLeviton app. This was created to fill a gap in the scheduling function of the MyLeviton app that does not observe a brightness level when automatically turning lights on and off on a schedule.

To compensate for differences in required brightness settings between fixtures, set the leviton_percent variable to the lowest value of all fixtures, and then set the minimum brightness level in the MyLeviton App for fixtures that require a higher brightness. This will make those fixtures that require a higher setting automatically adjust to that setting instead of the one defined.

3 Environmental Variables must be defined:

- leviton_username=[String]
- leviton_password=[String]
- leviton_percent=[Integer 0-100]
Note: For security purposes, pass these variables through a file using env-file in the run command

This docker uses the api for the MyLeviton app and does not require any open ports nor any direct communication with any of the switches to be controlled. On launch, the current day/night mode will be applied to all switches. This docker uses sunwait to determine the times for sunrise and sunset.

Future Developments:

Configuration generator
Individual device selection
Individual device brightness
Global time offset
Selection between civil, nautical, astronomical sunset/sunrise
Create docker with source code and enable autobuild
This docker is currently in Beta, meaning that while core functionality has been tested, there is potential for bugs or anomalies that were not anticipated being present in runtime that may cause the docker to exit unexpectedly. While this poses no threat to the function of your devices, if the docker is not configured to auto-restart, it may fail to turn on or off your fixtures at the expected times. There is no warranty or guarantees expressed or implied with the use of this docker or its constituent code.
