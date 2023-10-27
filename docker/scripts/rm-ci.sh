#!/bin/bash

docker rm $(docker ps -qa) & 
docker rmi -f $(docker images -qa)