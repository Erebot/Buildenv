# -*- coding: utf-8 -*-

import os
from os.path import join, dirname
import sys
import glob
import shutil
import urllib
import fnmatch
from datetime import datetime
from subprocess import call, Popen, PIPE

try:
    import simplejson as json
except ImportError:
    import json


class Context(dict):
    def __init__(self, globs, locs):
        super(Context, self).__init__()
        self.globals = globs
        self.locals = locs

    def update(self, e=None, **f):
        if e is None:
            e = {}
        super(Context, self).update(e, **f)

        if 'subprojects' in e:
            substitutions = \
                '\n'.join('.. |%s| replace:: %s' % sp
                          for sp in e['subprojects'])
            self.locals['rst_prolog'] = \
                self.locals.get('rst_prolog', '') + '\n' + substitutions


def prepare(globs, locs):
    # RTD defaults the current working directory to where conf.py resides.
    # In our case, that means <root>/docs/src/.
    cwd = os.getcwd()
    root = os.path.abspath(join(cwd, '..', '..'))

    git = Popen('which git 2> %s' % os.devnull, shell=True,
                stdout=PIPE).stdout.read().strip()
    doxygen = Popen('which doxygen 2> %s' % os.devnull, shell=True,
                stdout=PIPE).stdout.read().strip()

    locs['rtd_slug'] = os.path.basename(os.path.dirname(os.path.dirname(root)))
    locs['rtd_version'] = os.path.basename(root)
    pybabel = join(root, '..', '..', 'envs', locs['rtd_version'], 'bin', 'pybabel')
    builder = sys.argv[sys.argv.index('-b') + 1]

    print "Building version %s for %s in %s [builder: %s]" % (
        locs['rtd_version'],
        locs['rtd_slug'],
        root,
        builder
    )
    os.chdir(root)

    # Retrieve several values from git
    origin = Popen([git, 'config', '--local', 'remote.origin.url'],
                    stdout=PIPE).stdout.read().strip()
    git_tag = Popen([git, 'describe', '--tags', '--exact', '--first-parent'],
                    stdout=PIPE).communicate()[0].strip()
    git_hash = Popen([git, 'rev-parse', 'HEAD'],
                    stdout=PIPE).communicate()[0].strip()

    origin = origin.replace(':', '/').split('/')
    vendor = origin[-2]
    project = origin[-1]
    if project.endswith('.git'):
        project = project[:-4]
    os.environ['SPHINX_PROJECT'] = project
    if git_tag:
        os.environ['SPHINX_VERSION'] = git_tag
        os.environ['SPHINX_RELEASE'] = git_tag
    else:
        commit = Popen([git, 'describe', '--always', '--first-parent'],
                        stdout=PIPE).communicate()[0].strip()
        os.environ['SPHINX_VERSION'] = 'latest'
        os.environ['SPHINX_RELEASE'] = 'latest-%s' % (commit, )
        locs['tags'].add('devel')

    composer = json.load(open(join(root, 'composer.json'), 'r'))

    if builder == 'readthedocs':
        # Run doxygen
        call([doxygen, join(root, 'Doxyfile')], env={
            'COMPONENT_NAME': os.environ['SPHINX_PROJECT'],
            'COMPONENT_VERSION': os.environ['SPHINX_VERSION'],
            'COMPONENT_BRIEF': composer.get('description', ''),
        })

        # Copy API doc to final place,
        # overwriting files as necessary.
        try:
            shutil.rmtree(join(root, 'build'))
        except OSError:
            pass
        os.mkdir(join(root, 'build'))
        shutil.move(
            join(root, 'docs', 'api', 'html'),
            join(root, 'build', 'apidoc'),
        )
        try:
            shutil.move(
                join(root, '%s.tagfile.xml' %
                    os.environ['SPHINX_PROJECT']),
                join(root, 'build', 'apidoc', '%s.tagfile.xml' %
                    os.environ['SPHINX_PROJECT'])
            )
        except OSError:
            pass

    # Copy translations for generic docs to catalogs folder.
    gen_i18n = join(root, 'docs', 'src', 'generic', 'i18n', '.')[:-1]
    for translation in glob.iglob(join(gen_i18n, '*')):
        target_dir = join(
            root, 'docs', 'i18n',
            translation[len(gen_i18n):],
            'LC_MESSAGES', 'generic'
        )
        translation = join(translation, 'LC_MESSAGES', 'generic')
        shutil.rmtree(target_dir, ignore_errors=True)
        shutil.copytree(translation, target_dir)

    # Compile translation catalogs.
    for locale_dir in glob.iglob(join(root, 'docs', 'i18n', '*')):
        for base, dirnames, filenames in os.walk(locale_dir):
            for po in fnmatch.filter(filenames, '*.po'):
                po = join(base, po)
                mo = po[:-3] + '.mo'
                call([pybabel, 'compile', '-f', '--statistics',
                      '-i', po, '-o', mo])

    # Load the real Sphinx configuration file.
    os.chdir(cwd)
    real_conf = join(root, 'vendor', 'erebot', 'buildenv', 'sphinx', 'conf.py')
    print "Including real configuration file (%s)..." % (real_conf, )
    execfile(real_conf, globs, locs)

    # Patch configuration afterwards.
    # - Additional files (API doc)
    locs.setdefault('html_extra_path', []).append(join(root, 'build'))
    # - I18N
    locs.setdefault('locale_dirs', []).insert(0, join(root, 'docs', 'i18n'))
    # - misc.
    locs['rst_prolog'] = locs.get('rst_prolog', '') + \
        '\n    .. _`this_commit`: https://github.com/%s/%s/commit/%s\n' % (
            vendor,
            project,
            git_hash,
        )


old_html_context = globals().get('html_context', {})
html_context = Context(globals(), locals())
html_context.update(old_html_context)   
prepare(globals(), locals())
