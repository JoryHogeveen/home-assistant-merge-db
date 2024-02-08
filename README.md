# home-assistant-merge-db
Simple tool to merge two HA sqlite DB's in case of a corrupted file

This is the work of [Jory Hogeveen](https://github.com/JoryHogeveen)  
I just wrapped docker around it to make it easier to use.

- Install docker-compose
  - Easiest way is to install Docker Desktop, as it comes with docker-compose
- Navigate into the directory where the docker-compose.yml file is located (aka here)
- copy your corrupted db file and the new one into the `src` directory
- Run `docker-compose up`
- Open http://localhost:9000 in your browser and follow the instructions
