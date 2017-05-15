#!/usr/bin/env bash
set -e

UNUSED_USER_ID=21338
UNUSED_GROUP_ID=21337

echo "Fixing permissions."

# Setting Group Permissions
DOCKER_GROUP_CURRENT_ID=`id -g $DOCKER_GROUP`

if [ $DOCKER_GROUP_CURRENT_ID -eq $HOST_GROUP_ID ]; then
  echo "Group $DOCKER_GROUP is already mapped to $DOCKER_GROUP_CURRENT_ID. Nice!"
else
  echo "Check if group with ID $HOST_GROUP_ID already exists"
  DOCKER_GROUP_OLD=`getent group $HOST_GROUP_ID | cut -d: -f1`

  if [ -z "$DOCKER_GROUP_OLD" ]; then
    echo "Group ID is free. Good."
  else
    echo "Group ID is already taken by group: $DOCKER_GROUP_OLD"

    echo "Changing the ID of $DOCKER_GROUP_OLD group to 21337"
    groupmod -o -g $UNUSED_GROUP_ID $DOCKER_GROUP_OLD
  fi

  echo "Changing the ID of $DOCKER_GROUP group to $HOST_GROUP_ID"
  groupmod -o -g $HOST_GROUP_ID $DOCKER_GROUP || true
  echo "Finished"
  echo "-- -- -- -- --"
fi

# Setting User Permissions
DOCKER_USER_CURRENT_ID=`id -u $DOCKER_USER`

if [ $DOCKER_USER_CURRENT_ID -eq $HOST_USER_ID ]; then
  echo "User $DOCKER_USER is already mapped to $DOCKER_USER_CURRENT_ID. Nice!"

else
  echo "Check if user with ID $HOST_USER_ID already exists"
  DOCKER_USER_OLD=`getent passwd $HOST_USER_ID | cut -d: -f1`

  if [ -z "$DOCKER_USER_OLD" ]; then
    echo "User ID is free. Good."
  else
    echo "User ID is already taken by user: $DOCKER_USER_OLD"

    echo "Changing the ID of $DOCKER_USER_OLD to 21337"
    usermod -o -u $UNUSED_USER_ID $DOCKER_USER_OLD
  fi

  echo "Changing the ID of $DOCKER_USER user to $HOST_USER_ID"
  usermod -o -u $HOST_USER_ID $DOCKER_USER || true
  echo "Finished"
fi
