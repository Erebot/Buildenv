# -*- coding: utf-8 -*-
"""
    doxylinks
    ~~~~~~~~~

    Extension to save typing when linking to some information contained in
    a Doxygen documentation.

    This adds two new config values.
    The first one is called ``doxylinks`` and is created like this::

       doxylinks = {'exmpl': ('http://example.com/', '/path/to/tagfile'), ...}

    The second one is called ``doxylinks_cache_limit`` and indicates
    the number of days for which a remote tagfile will be kept in cache
    before being considered invalid (and fetched again).
    The default value is 1, meaning that remote tagfiles will only be
    fetched once per day.

    Now you can use e.g. :exmpl:`foo` in your documents.  This will create a
    link to a page hosted at ``http://example.com/`` and containing the
    documentation about the symbol named ``foo``.
    The link caption will be he symbol's name, unless an explicit caption
    is given, e.g. :exmpl:`Foo <foo>`.

    The full path to the symbol is retrieved from the Doxygen tagfile located
    at ``/path/to/tagfile``, which can be either a local file or an URL
    to some online file.

    If ``add_function_parentheses`` is set to ``True`` in your configuration
    file and if no explicit caption was given, the symbol will be suffixed
    with a set of parentheses whenever this is appropriate.

    This extension is heavily based on the extlinks and intersphinx extensions.

    :copyright: Copyright 2013 by Francois Poirotte.
    :license: BSD, see LICENSE for details.
"""

import os
import time
import urllib2
import libxml2
from docutils import nodes, utils
from sphinx.util.nodes import split_explicit_title

__all__ = ['setup']


def configure_urllib2():
    handlers = [urllib2.ProxyHandler(), urllib2.HTTPRedirectHandler(),
                urllib2.HTTPHandler()]
    try:
        handlers.append(urllib2.HTTPSHandler)
    except AttributeError:
        pass
    urllib2.install_opener(urllib2.build_opener(*handlers))

def load_mappings(app):
    """Load all tagfiles into the environment."""
    configure_urllib2()
    now = int(time.time())
    cache_time = now - getattr(app.config, 'doxylinks_cache_limit', 1) * 86400
    env = app.builder.env
    if not hasattr(env, 'doxylinks_cache'):
        env.doxylinks_cache = {}
    cache = env.doxylinks_cache
    for (_dummy, tagfile) in app.config.doxylinks.itervalues():
        # decide whether the tagfile must be read: always read local
        # files; remote ones only if the cache time is expired
        if '://' not in tagfile or tagfile not in cache \
               or cache[tagfile][1] < cache_time:
            app.info('loading tagfile from %s...' % tagfile)
            data = fetch_tagfile(app, tagfile)
            if data:
                cache[tagfile] = (data, now)
            else:
                cache.pop(tagfile, None)
        else:
            app.info('loading tagfile %s from cache...' % tagfile)

def fetch_tagfile(app, tagfile):
    """Fetch and store a Doxygen tagfile in memory."""
    try:
        if tagfile.find('://') != -1:
            f = urllib2.urlopen(tagfile)
        else:
            f = open(os.path.join(app.srcdir, tagfile), 'rb')
        try:
            return f.read()
        except Exception:
            raise
        finally:
            f.close()
    except Exception, err:
        app.warn('Doxygen tagfile %r not fetchable due to '
                 '%s: %s' % (tagfile, err.__class__, err))
        return

def lookup_url(app, tagfile, symbol):
    env = app.builder.env
    cache = env.doxylinks_cache
    doc = libxml2.parseDoc(cache[tagfile][0])
    ctxt = doc.xpathNewContext()
    cls, _sep, member = symbol.partition('::')
    query = (
        "/tagfile/"
        "compound[@kind='interface' or @kind='class' or @kind='page']"
        "/name[text()='%(class)s']"
        "/.."
    ) % {'class': cls}
    try:
        if member:
            query += (
                "/member[@kind='function' or @kind='variable']"
                "/name[text()='%(member)s']"
                "/.."
            ) % {'class': cls, 'member': member}
            res = ctxt.xpathEval(query + "/anchorfile/text()")
            filename = str(res[0].content)
            res = ctxt.xpathEval(query + "/anchor/text()")
            anchor = str(res[0].content)
            res = ctxt.xpathEval(query + "/@kind")
            kind = str(res[0].content)
        else:
            res = ctxt.xpathEval(query + "/filename/text()")
            filename = str(res[0].content)
            anchor = None
            res = ctxt.xpathEval(query + "/@kind")
            kind = str(res[0].content)
    except IndexError:
        raise KeyError('No documentation found for "%s"' % symbol)
    finally:
        doc.freeDoc()
        ctxt.xpathFreeContext()
    if not filename.endswith('.html'):
        filename += '.html'
    return (kind, filename, anchor)

def make_link_role(app, tagfile, base_url):
    def role(typ, rawtext, text, lineno, inliner, options={}, content=[]):
        text = utils.unescape(text)
        has_explicit_title, symbol, title = split_explicit_title(text)
        kind, filename, anchor = lookup_url(app, tagfile, symbol)
        full_url = base_url + filename
        if anchor:
            full_url += '#' + anchor
        if not has_explicit_title:
            title = symbol
            if app.config.add_function_parentheses and kind == 'function':
                title += '()'
        pnode = nodes.reference(title, title, internal=False, refuri=full_url)
        return [pnode], []
    return role

def setup_link_roles(app):
    for name, (base_url, tagfile) in app.config.doxylinks.iteritems():
        app.add_role(name, make_link_role(app, tagfile, base_url))

def setup(app):
    app.add_config_value('doxylinks', {}, 'env')
    app.connect('builder-inited', load_mappings)
    app.connect('builder-inited', setup_link_roles)

