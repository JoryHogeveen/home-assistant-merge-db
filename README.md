# home-assistant-merge-db
Simple tool to merge two HA sqlite DB's in case of a corrupted file

- Install docker-compose
  - Easiest way is to install Docker Desktop, as it comes with docker-compose
- Navigate into the directory where the docker-compose.yml file is located (aka here) via terminal
- Shutdown Home Assistant
- copy your corrupted db file and the new one into the `src` directory
- Run `docker-compose up`
- Open http://localhost:9000 in your browser and follow the instructions
- When done move the new db file back to your Home Assistant directory, rename it to `home-assistant_v2.db` and start Home Assistant again
- Close the docker-compose terminal with `Ctrl+C` and run `docker-compose down` to clean up
- Done!

### Do it manually
You can also use your own webserver to host the files manually, just copy the `src` directory to your webserver and do webserver things with it. If you want to go this route you probably know what you are doing.

### Troubleshooting
All the data from the corrupt database is now "long-term statistics" in Home Assistant. This means that it will not show up in the normal views, but you can still access it via "show more" on most cards or entities.
