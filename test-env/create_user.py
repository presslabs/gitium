from __future__ import print_function

import subprocess
import sys
import os
import grp


def shell(cmd, die=True, encoding='utf8'):
    try:
        # print(cmd)
        out = subprocess.check_output(cmd, stderr=subprocess.STDOUT, shell=True)
        # print(out)
        # print("")
        return str(out, encoding=encoding)
    except subprocess.CalledProcessError as err:
        # print(err.output)
        # print("")
        if die is True:
            raise
        else:
            return str(err.output, encoding=encoding)


def get_bindmount_user_id():
    out = shell('ls -lnd /code')
    uid, gid = out.split()[2:4]
    return uid, gid


def create_user():
    uid, gid = get_bindmount_user_id()
    username = 'developer'
    groupname = 'developer'
    try:
        groupname = grp.getgrgid(gid).gr_name
        print("group with id '{}' already exists: {}".format(gid, groupname))
    except KeyError:
        shell('groupadd {} --gid {}'.format(groupname, gid))
    out = shell('id -un {}'.format(uid), die=False)
    if out == "id: '{}': no such user\n".format(uid):
        print('creating user "{}"'.format(username))
        shell("useradd {} --home /code --uid {} --gid {} --shell=/bin/bash".format(
            username, uid, gid
        ))
    else:
        username = out
        print('user with id {} already exists: {}'.format(uid, username))
    return username, groupname


def exec_to_bash():
    print("\n######### STARTING TESTING ENVIRONMENT #########\n")
    username, groupname = create_user()
    print("Starting mysql!")
    shell("/etc/init.d/mysql start")
    print("Dropping you to an interactive shell as '{}'.".format(username))
    print("Type CTRL+D to return to root shell.\n")
    os.chdir('/code')
    sys.stdout.flush()
    cmd = ["/bin/bash", "bash", "-c", "su {}; bash".format(username)]
    os.execl(*cmd)

if __name__ == '__main__':
    exec_to_bash()
