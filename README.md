# Prerequisites

No.
Calculations are done locally.

# How does it work

This image publishes once a day some sun informations:  
- all sunrise times 
- all sunset times
- transit time, daylength
And every minutes some live informations:
- elevation and azimuth

![Diagram](https://raw.githubusercontent.com/Domochip/sun2mqtt/master/diagram.svg)

# How-to
## Install
For Docker, run it by executing the following commmand:

```bash
docker run \
    -d \
    --name sun2mqtt \
    --restart=always \
    -e TZ="Europe/Paris" \
    -e PUBLISHHOUR=3 \
    -e LATITUDE="48.85826" \
    -e LONGITUDE="2.29451" \
    -e HOST="192.168.1.x" \
    -e PORT=1883 \
    -e PREFIX="sun2mqtt" \
    -e CLIENTID="sun2mqttclid" \
    -e USER="usr" \
    -e PASSWORD="pass" \
    domochip/sun2mqtt
```
For Docker-Compose, use the following yaml:

```yaml
version: '3'
services:
  sun2mqtt:
    container_name: sun2mqtt
    image: domochip/sun2mqtt
    environment:
    - TZ=Europe/Paris
    - PUBLISHHOUR=0
    - LATITUDE=48.85826
    - LONGITUDE=2.29451
    - HOST=192.168.1.x
    - PORT=1883
    - PREFIX=sun2mqtt
    - CLIENTID=sun2mqttclid
    - USER=mqtt_username
    - PASSWORD=mqtt_password
    restart: always
```

### Configure

#### Environment variables
* `TZ`: **Optional**, (Linux TimeZone) Timezone used to schedule publish and produce local time results
* `PUBLISHHOUR`: **Optional**, (Integer: 0 to 23) hour of publish everyday
* `LATITUDE`: **Optional**, (Float) latitude of the position for calculation
* `LONGITUDE`: **Optional**, (Float) longitude of the position for calculation
* `HOST`: IP address or hostname of your MQTT broker
* `PORT`: **Optional**, port of your MQTT broker
* `PREFIX`: **Optional**, prefix used in topics for subscribe/publish
* `CLIENTID`: **Optional**, MQTT client id to use
* `USER`: **Optional**, MQTT username
* `PASSWORD`: **Optional**, MQTT password

## Published Informations

### Technical information

* `{prefix}/connected`: 0 or 1, Indicates connection status of the container
* `{prefix}/executionTime`: DateTime, execution time of the publication

### Sunrise

Sunrise information: time formatted as "hhmm" (2 hours digits followed by 2 minutes digits)  
* `{prefix}/sun/astronomicsunrise`: Astronomic sunrise
* `{prefix}/sun/nauticsunrise`: Nautic Sunrise
* `{prefix}/sun/civilsunrise`: Civil sunrise
* `{prefix}/sun/sunrise`: Sunrise

### Sunset

Sunset information: time formatted as "hhmm" (2 hours digits followed by 2 minutes digits)  
* `{prefix}/sun/sunset`: Sunset
* `{prefix}/sun/civilsunset`: Civil sunset
* `{prefix}/sun/nauticsunset`: Nautic sunset
* `{prefix}/sun/astronomicsunset`: Astronomic sunset

### Other daily infos

Other daily information:  
* `{prefix}/sun/transit`: time formatted as "hhmm" (2 hours digits followed by 2 minutes digits), time when the sun is at its higher position
* `{prefix}/sun/daylength`: daylength in minutes (time between sunrise and sunset)

### Live Sun Position

Live sun information published every minutes:  
* `{prefix}/sunposition/elevation`: 0-90, Indicates the angle of the sun measured from the horizon
* `{prefix}/sunposition/azimuth`: 0-360, The azimuth angle is the compass direction from which the sunlight is coming
* `{prefix}/sunposition/daytime`: 0 or 1, indicates if sun is in the sky (between sunrise and sunset)

# Troubleshoot
## Logs
You need to have a look at logs using :  
`docker logs sun2mqtt`

# Updating
To update to the latest Docker image:
```bash
docker stop sun2mqtt
docker rm sun2mqtt
docker rmi domochip/sun2mqtt
# Now run the container again, Docker will automatically pull the latest image.
```
# Ref/Thanks

I want to thanks a lot **Lunarok** for his Jeedom plugin Heliotrope which is the original code/idea of this Docker Image :  
* https://github.com/lunarok/jeedom_heliotrope
* https://market.jeedom.com/index.php?v=d&p=market_display&id=1482
