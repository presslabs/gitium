## Testing

### Build the docker image
```
cd test-env
docker build -t gitiumtest .
```

### Run the docker image and associate the code with /code dir
```
cd gitium
docker run -it -v `pwd`:/code gitiumtest
```

### Start the env for tests
```
make clean ; make env_latest
```

### Run the tests
```
reset ; ./vendor/bin/phpunit --tap
```

### Run only one suite (clean run)
```
make clean ; make env_latest ; reset ; ./vendor/bin/phpunit --tap tests/test-git-wrapper.php
```

### Run only the methods with test_is_dirty
```
reset ; ./vendor/bin/phpunit --tap tests/test-git-wrapper.php --filter '/test_is_dirty/'
```

### Build your own WordPress local environment using docker (mywordpressdocker)
```
cd test-env
./wordpress-docker.sh
```

### The result should look similar to this:
```
Go to: http://172.17.0.4/ in order to install a new WordPress site.
```

#### Go inside of mywordpressdocker
```
docker exec -it $(docker ps -a | grep mywordpressdocker | cut -d' ' -f1) bash
```

#### View the logs inside of mywordpressdocker
```
docker logs -f $(docker ps -a | grep mywordpressdocker | cut -d' ' -f1)
docker logs -f $(docker ps -a | grep mywordpressdocker | cut -d' ' -f1) 2>&1 | grep 'PHP error'
```

#### Connect to MySQL from docker
```
docker exec -it $(docker ps -a | grep mysqldocker | cut -d' ' -f1) bash
mysql --user=root --password=my-secret-pw --database=wordpress
```
