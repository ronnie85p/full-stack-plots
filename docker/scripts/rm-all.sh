#!/bin/bash

docker rm $(docker ps -qa) & 
docker rmi -f $(docker images -qa) & 
docker volume rm $(docker volume ls -q) & 
docker network rm $(docker network ls -q)