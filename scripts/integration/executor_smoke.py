"""Disposable real CP + GeekExam + GeekExecutor stand; see docs/executor-qa.md."""
import argparse
import base64
from concurrent.futures import ThreadPoolExecutor
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import hashlib
import io
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import socket
import sqlite3
import subprocess
import sys
import tempfile
import threading
import time
import uuid
from urllib.parse import urlsplit, parse_qs

import jwt
import requests
import socketio
from PIL import Image
from openpyxl import Workbook

ROOT = Path(__file__).resolve().parents[2]
PHP = ROOT / 'scripts/integration/executor'


def free_port():
    with socket.socket() as server:
        server.bind(('127.0.0.1', 0))
        return server.getsockname()[1]


def wait_http(url, status=None):
    for _ in range(200):
        try:
            response = requests.get(url, timeout=1)
            if status is None or response.status_code == status:
                return
        except requests.RequestException:
            pass
        time.sleep(.1)
    raise AssertionError('Service failed to start: ' + url)


class Watch:
    def __init__(self, subscription):
        self.subscription = subscription
        self.events, self.done = [], threading.Event()
        self.client = socketio.Client(reconnection=False)
        self.client.on('execution_progress', self.progress, namespace='/executions')
        self.connect()

    def progress(self, event):
        assert event['execution_id'] == self.subscription['execution_id']
        assert set(event) <= {'execution_id', 'sequence', 'phase', 'completed', 'total'}
        self.events.append((time.monotonic(), event))
        if event['phase'] in ('completed', 'error'):
            self.done.set()

    def connect(self):
        self.client.connect(self.subscription['url'], namespaces=['/executions'], transports=['websocket'],
                            auth={'token': self.subscription['token']}, wait_timeout=10)
        assert self.client.transport() == 'websocket'

    def wait(self):
        assert self.done.wait(150), 'No terminal WebSocket event'
        assert self.events[-1][1]['phase'] == 'completed', self.events

    def close(self):
        if self.client.connected:
            self.client.disconnect()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--executor-dir', type=Path, required=True)
    parser.add_argument('--exam-dir', type=Path, required=True)
    parser.add_argument('--docker-context', required=True)
    parser.add_argument('--output', type=Path, default=ROOT / 'output/integration/executor')
    args = parser.parse_args()
    args.output.mkdir(parents=True, exist_ok=True)
    (args.output/'result.json').unlink(missing_ok=True)
    executor, exam = args.executor_dir.resolve(), args.exam_dir.resolve() / 'backend'
    for tool in ('php', 'redis-server', 'docker'):
        if not shutil.which(tool):
            raise SystemExit('Missing executable: ' + tool)
    docker = ['docker', '--context', args.docker_context]
    subprocess.run(docker + ['info'], check=True, stdout=subprocess.DEVNULL)
    processes, logs, watches = [], [], []
    container = 'codex-executor-qa-' + secrets.token_hex(4)
    cp_secret, exam_secret, login_secret = 'qa-cp-executor-' * 4, 'qa-exam-executor-' * 4, 'qa-login-only-' * 4
    urls = {key: f'http://127.0.0.1:{free_port()}' for key in ('cp', 'exam', 'executor', 's3', 'ai')}
    redis_port, started, ai_requests = free_port(), time.monotonic(), []

    class AI(BaseHTTPRequestHandler):
        def log_message(self, *args):
            pass

        def do_POST(self):
            ai_requests.append(json.loads(self.rfile.read(int(self.headers['Content-Length']))))
            result = {'status': 'completed', 'output': [{'type': 'message', 'status': 'completed',
                'content': [{'type': 'output_text', 'text': '20\nПроверено локальным тестовым провайдером.'}]}]}
            raw = json.dumps(result).encode()
            self.send_response(200)
            self.send_header('Content-Type', 'application/json')
            self.send_header('Content-Length', str(len(raw)))
            self.end_headers()
            self.wfile.write(raw)

    ai = ThreadingHTTPServer(('127.0.0.1', int(urls['ai'].rsplit(':', 1)[1])), AI)
    threading.Thread(target=ai.serve_forever, daemon=True).start()
    try:
        with tempfile.TemporaryDirectory(prefix='cp-executor-integration-') as temporary:
            folder = Path(temporary)

            def start(command, name, cwd, env):
                log = (folder / (name + '.log')).open('w+')
                logs.append((name, log))
                process = subprocess.Popen(command, cwd=cwd, env=env, stdout=log, stderr=log)
                processes.append(process)
                return process

            common = dict(os.environ, PYTHONUNBUFFERED='1', OBJC_DISABLE_INITIALIZE_FORK_SAFETY='YES',
                          DOCKER_CONTEXT=args.docker_context, DOCKER_TRANSFER_MODE='cp')
            cp_env = dict(common, EXECUTOR_QA_MODE='1', EXECUTOR_QA_DIRECTORY=str(folder), APP_ENV='testing',
                APP_DEBUG='false', APP_KEY='base64:' + base64.b64encode(b'x' * 32).decode(),
                DB_CONNECTION='sqlite', DB_DATABASE=str(folder / 'submissions.sqlite'), CACHE_DRIVER='array',
                CACHE_STORE='array', QUEUE_CONNECTION='database', SESSION_DRIVER='file')
            s3_key, s3_secret, bucket = 'GK' + secrets.token_hex(16), secrets.token_hex(32), 'cp-executor-qa'
            settings = dict(cp_url=urls['cp'], executor_url=urls['executor'], cp_secret=cp_secret,
                s3_url=urls['s3'], s3_key=s3_key, s3_secret=s3_secret, s3_bucket=bucket)
            (folder / 'settings.json').write_text(json.dumps(settings))
            (folder / 'settings.json').chmod(0o600)
            config = f'''metadata_dir="/tmp/garage-meta"
data_dir="/tmp/garage-data"
db_engine="sqlite"
replication_factor=1
rpc_bind_addr="[::]:3901"
rpc_public_addr="127.0.0.1:3901"
rpc_secret="{secrets.token_hex(32)}"
[s3_api]
s3_region="garage"
api_bind_addr="[::]:3900"
'''
            (folder / 'garage.toml').write_text(config)
            subprocess.run(docker + ['create', '--name', container, '-p',
                '127.0.0.1:' + urls['s3'].rsplit(':', 1)[1] + ':3900',
                '-e', 'GARAGE_DEFAULT_ACCESS_KEY=' + s3_key, '-e', 'GARAGE_DEFAULT_SECRET_KEY=' + s3_secret,
                '-e', 'GARAGE_DEFAULT_BUCKET=' + bucket, 'dxflrs/garage:v2.5.0',
                '/garage', 'server', '--single-node', '--default-bucket'], check=True, stdout=subprocess.DEVNULL)
            subprocess.run(docker + ['cp', str(folder / 'garage.toml'), container + ':/etc/garage.toml'], check=True, stdout=subprocess.DEVNULL)
            subprocess.run(docker + ['start', container], check=True, stdout=subprocess.DEVNULL)
            wait_http(urls['s3'])
            subprocess.run(['php', str(ROOT / 'scripts/ui-checks/prepare.php'), str(folder)],
                           cwd=ROOT, env=cp_env, check=True, stdout=subprocess.DEVNULL)
            subprocess.run(['php', str(PHP / 'seed.php')], cwd=ROOT, env=cp_env, check=True, stdout=subprocess.DEVNULL)
            exam_env = dict(common, SECRET_KEY=login_secret, JWT_SECRET=login_secret,
                CONNECTION_STRING='sqlite:///' + str(folder / 'exam.sqlite') + '?timeout=30',
                REDIS_URL=f'redis://127.0.0.1:{redis_port}/1', CELERY_BROKER=f'redis://127.0.0.1:{redis_port}/1',
                GEEKEXECUTOR_URL=urls['executor'], GEEKEXECUTOR_SOCKET_URL=urls['executor'],
                GEEKEXECUTOR_SECRET=exam_secret, EXECUTOR_CALLBACK_BASE_URL=urls['exam'],
                EXECUTOR_STORAGE_DIR=str(folder / 'exam-files'))
            clients = {'codingprojects': dict(base_url=urls['cp'], jwt_secret=cp_secret, api_prefix='/api/executor'),
                       'geekexam': dict(base_url=urls['exam'], jwt_secret=exam_secret, api_prefix='/api/executor')}
            executor_env = dict(common, SESSION_COOKIE_SECURE='false', SECRET=cp_secret, JWT_SECRET=cp_secret, CODING_PROJECTS_URL=urls['cp'],
                GEEKCLASS_HOST=urls['cp'], CONNECTION_STRING='sqlite:///' + str(folder / 'executor.sqlite') + '?timeout=30',
                REDIS_URL=f'redis://127.0.0.1:{redis_port}/0', CELERY_BROKER=f'redis://127.0.0.1:{redis_port}/0',
                TEST_ARTIFACTS_DIR=str(folder / 'executor-cache'), SOCKET_ALLOWED_ORIGINS='*',
                CHECKER_CLIENTS_JSON=json.dumps(clients), AI_PROVIDER='openai', OPENAI_API_KEY='qa-only-local',
                OPENAI_RESPONSES_URL=urls['ai'] + '/v1/responses', OPENAI_PROXY='', GPT_PROXY='',
                GPT_KEY='qa-only-local', GPT_GATEWAY=urls['ai'] + '/v1/responses', GPT_MODEL='qa-local')
            start(['redis-server', '--bind', '127.0.0.1', '--port', str(redis_port), '--save', '', '--appendonly', 'no'], 'redis', ROOT, common)
            seed = '''from manage import app
from models import db,User,Test,Variant,Question,Attempt,Answer
import execution_models
with app.app_context():
 db.create_all()
 db.session.add_all([User(id=1,name='Teacher',role='teacher'),User(id=2,name='Student',role='student')])
 t=Test(id=1,title='Disposable live test',created_by=1,is_active=True,code='LIVE');v=Variant(id=1,title='One',test=t)
 q=Question(id=1,variant=v,title='Sum',body='Add two integers',type='code_input',max_points=3,check_type='docker',check_config=dict(lang='python',submission_modes=['files'],tests=[dict(input='2 3',expected='5'),dict(input='17 25',expected='42',hidden=True)],time_limit=5,score_step=.5))
 a=Attempt(id=1,test=t,variant=v,user_id=2,max_points=3);answer=Answer(id=1,attempt=a,question=q,value=None)
 db.session.add_all([t,a,answer]);db.session.commit()
'''
            subprocess.run([sys.executable, '-c', seed], cwd=exam, env=exam_env, check=True, stdout=subprocess.DEVNULL)
            subprocess.run([sys.executable, '-m', 'flask', '--app', 'executor_server', 'db', 'upgrade'],
                           cwd=executor, env=executor_env, check=True, stdout=subprocess.DEVNULL)
            start(['php', '-S', urls['cp'].split('//')[1], '-t', str(ROOT / 'public'), str(PHP / 'router.php')], 'cp-http', ROOT, cp_env)
            start(['php', str(PHP / 'console.php'), 'queue:work', '--sleep=1', '--tries=4', '--timeout=240'], 'cp-worker', ROOT, cp_env)
            start([sys.executable, '-m', 'gunicorn', '--bind', urls['exam'].split('//')[1], '--worker-class', 'eventlet', '--workers', '1', 'app:app'], 'exam-http', exam, exam_env)
            start([sys.executable, '-m', 'celery', '-A', 'celery_app.celery', 'worker', '--pool=solo', '--loglevel=WARNING'], 'exam-worker', exam, exam_env)
            start([sys.executable, '-m', 'gunicorn', '--bind', urls['executor'].split('//')[1], '--worker-class', 'gthread', '--threads', '8', '--workers', '1', 'executor_server:app'], 'executor-http', executor, executor_env)
            start([sys.executable, '-m', 'celery', '-A', 'executor_queue.celery', 'worker', '--pool=prefork', '--concurrency=2', '--loglevel=WARNING'], 'executor-worker', executor, executor_env)
            wait_http(urls['executor'] + '/health', 200)
            wait_http(urls['exam'] + '/api/me', 401)
            wait_http(urls['cp'] + '/api/executor/executions/no/input', 401)

            def cp_client(actor):
                client = requests.Session()
                path = '/insider/courses/1/tasks/12/edit' if actor == 'teacher' else '/insider/code-submissions/10000000-0000-4000-8000-000000000001'
                page = client.get(urls['cp'] + path, params={'actor': actor}, timeout=15)
                assert page.status_code == 200, page.text[:1000]
                token = re.search(r'name="csrf-token" content="([^"]+)"', page.text)
                assert token, 'CSRF token missing'
                client.headers.update({'X-CSRF-TOKEN': token[1], 'Accept': 'application/json'})
                return client

            student, teacher = cp_client('student'), cp_client('teacher')

            def call(client, method, url, expected=200, **options):
                response = client.request(method, url, timeout=20, **options)
                assert response.status_code == expected, (method, url, response.status_code, response.text[:1500])
                return response

            def cp_view(item):
                return call(student, 'GET', urls['cp'] + '/insider/code-submissions/' + item['id']).json()

            def balance():
                with sqlite3.connect(folder / 'submissions.sqlite') as db:
                    return db.execute('SELECT SUM(price) FROM coin_transactions WHERE user_id=1').fetchone()[0]

            print('Stand ready: private S3, Laravel worker and two Celery processes.', flush=True)
            material = b'%PDF-1.7\noriginal bytes\x00\xff'
            attachment = call(teacher, 'POST', urls['cp'] + '/insider/courses/1/tasks/12/checker/files', 201,
                files={'file': ('материал.pdf', material)}, data=dict(path='material.pdf', kind='attachment', is_public='1')).json()
            link = call(student, 'GET', attachment['download_url'], 302, allow_redirects=False).headers['Location']
            assert 'X-Amz-Signature=' in link and 'X-Amz-Expires=300' in link
            assert requests.get(link, timeout=10).content == material
            assert requests.get(link.split('?')[0], timeout=10).status_code in (400, 403)
            exam_student = requests.Session()
            token = jwt.encode(dict(id=2, name='Student', role='student', iat=int(time.time())), login_secret, algorithm='HS256')
            call(exam_student, 'GET', urls['exam'] + '/auth/callback', 302, params=dict(token=token), allow_redirects=False)
            source = b'import time\na,b=map(int,input().split())\ntime.sleep(1.5)\nprint(a+b)\n'
            upload = call(exam_student, 'POST', urls['exam'] + '/api/answers/1/files', 201, files={'file': ('main.py', source)}).json()
            call(exam_student, 'PUT', urls['exam'] + '/api/answers/1', json={'value': dict(mode='files', files=[{'upload_id': upload['id']}])})
            partial = b'import time\na,b=map(int,input().split())\ntime.sleep(1.5)\nprint(a+b if a<10 else 0)\n'
            (folder / 'fail-next-callback').touch()
            with ThreadPoolExecutor(2) as pool:
                cp_future = pool.submit(call, student, 'POST', urls['cp'] + '/insider/courses/1/tasks/12/code-submissions', 201,
                    files={'files[]': ('main.py', partial)}, data=dict(request_id=str(uuid.uuid4()), mode='files', language='python'))
                exam_future = pool.submit(call, exam_student, 'POST', urls['exam'] + '/api/attempts/1/finish')
                first, exam_link = cp_future.result().json(), exam_future.result().json()['executions'][0]
            outsider=requests.Session();outsider.cookies.set('executor_qa_actor','other')
            call(outsider,'GET',urls['cp']+'/insider/code-submissions/'+first['id'],403,headers={'Accept':'application/json'})
            cp_watch, exam_watch = Watch(first['subscription']), Watch(exam_link['subscription'])
            watches.extend([cp_watch, exam_watch])
            time.sleep(.5)
            cp_watch.client.disconnect()
            cp_watch.connect()
            cp_watch.wait()
            exam_watch.wait()
            first_view = cp_view(first)
            assert first_view['points'] == 10 and first_view['awarded_xp'] == 10, first_view
            assert (folder / 'callback-retried').is_file()
            assert not first_view['report']['tests'][1]['unlocked'] and '17 25' not in json.dumps(first_view)
            before, hidden = balance(), first_view['report']['tests'][1]
            path = urls['cp'] + f'/insider/code-test-reports/{first["execution_id"]}/tests/{hidden["test_id"]}/purchase'
            assert call(student, 'POST', path, json={'expected_price': 5}).json()['charged'] == 5
            assert balance() == before - 5
            assert call(student, 'POST', path, json={'expected_price': 5}).json()['charged'] == 0
            assert '17 25' in json.dumps(cp_view(first))
            cpp = '#include <iostream>\n#include "add.h"\nint main(){int a,b;std::cin>>a>>b;std::cout<<add(a,b)<<"\\n";}\n'
            second = call(student, 'POST', urls['cp'] + '/insider/courses/1/tasks/12/code-submissions', 201,
                json=dict(request_id=str(uuid.uuid4()), mode='project', language='cpp', editor_files=[
                    dict(path='main.cpp', content=cpp), dict(path='add.h', content='int add(int a,int b){return a+b;}')])).json()
            cpp_watch = Watch(second['subscription'])
            watches.append(cpp_watch)
            cpp_watch.wait()
            second_view = cp_view(second)
            assert second_view['points'] == 20 and second_view['awarded_xp'] == 20, second_view
            assert any(event['phase'] == 'compiling' for _, event in cpp_watch.events)
            with sqlite3.connect(folder / 'submissions.sqlite') as db:
                baseline = [db.execute('SELECT COUNT(*) FROM ' + table).fetchone()[0] for table in ('coin_transactions', 'reward_notices', 'solutions')]
                assert db.execute("SELECT COUNT(*) FROM coin_transactions WHERE user_id=1 AND comment='Task #12'").fetchone()[0] == 1
            raw = (folder / ('callback-' + second['execution_id'] + '.json')).read_bytes()
            now = int(time.time())
            signed = jwt.encode(dict(iss='geekexecutor', aud='codingprojects', purpose='execution-result',
                iat=now, exp=now + 60, body_sha256=hashlib.sha256(raw).hexdigest()), cp_secret, algorithm='HS256')
            call(requests, 'POST', urls['cp'] + f'/api/executor/executions/{second["execution_id"]}/result',
                data=raw, headers={'Authorization': 'Bearer ' + signed, 'Content-Type': 'application/json', 'Accept': 'application/json'})
            with sqlite3.connect(folder / 'submissions.sqlite') as db:
                assert baseline == [db.execute('SELECT COUNT(*) FROM ' + table).fetchone()[0] for table in ('coin_transactions', 'reward_notices', 'solutions')]
            call(teacher, 'POST', urls['cp'] + '/insider/courses/1/tasks/12/checker/files/' + attachment['id'] + '/replace', 201,
                files={'file': ('new.pdf', b'new original')}, data=dict(path='material.pdf', kind='attachment', is_public='1'))
            subprocess.run(docker + ['restart', container], check=True, stdout=subprocess.DEVNULL)
            wait_http(urls['s3'])
            old = call(student, 'GET', urls['cp'] + f'/insider/code-submissions/{first["id"]}/task-files/0', 302, allow_redirects=False)
            assert requests.get(old.headers['Location'], timeout=10).content == material
            call(teacher, 'PUT', urls['cp'] + '/insider/courses/1/tasks/12/checker/private',
                json=dict(language='text', check_type='gpt', submission_modes=['files'], private_instructions='QA PRIVATE RUBRIC', gpt_model='qa-local'))
            picture = io.BytesIO()
            Image.new('RGB', (2, 2), 'green').save(picture, format='PNG')
            workbook, excel = Workbook(), io.BytesIO()
            workbook.active['A1'] = 'QA_WORKBOOK_VALUE'
            workbook.save(excel)
            notebook = json.dumps(dict(nbformat=4, nbformat_minor=5, metadata={}, cells=[dict(
                cell_type='code', metadata={}, source=['QA_NOTEBOOK_VALUE = 3'], execution_count=None, outputs=[])])).encode()
            bundle = call(student, 'POST', urls['cp'] + '/insider/courses/1/tasks/12/code-submissions', 201,
                data=dict(request_id=str(uuid.uuid4()), mode='files', text='QA bundle explanation'), files=[
                    ('files[]', ('image.png', picture.getvalue())), ('files[]', ('report.xlsx', excel.getvalue())), ('files[]', ('report.ipynb', notebook))]).json()
            ai_watch = Watch(bundle['subscription'])
            watches.append(ai_watch)
            ai_watch.wait()
            assert cp_view(bundle)['points'] == 20
            provider = json.dumps(ai_requests, ensure_ascii=False)
            assert all(value in provider for value in ('input_image', 'QA_WORKBOOK_VALUE', 'QA_NOTEBOOK_VALUE', 'QA PRIVATE RUBRIC'))
            public = call(exam_student, 'GET', urls['exam'] + '/api/executions/' + exam_link['execution_id']).json()
            assert public['statistics']['tests_passed'] == 2 and '17 25' not in json.dumps(public)
            call(exam_student, 'POST', urls['exam'] + '/api/callback/check', 404, json={'callback_id': 1, 'points': 3})
            with sqlite3.connect(folder / 'exam.sqlite') as db:
                assert db.execute('SELECT points,check_state FROM answers WHERE id=1').fetchone() == (3.0, 'checked')

            def interval(watch):
                times = [stamp for stamp, event in watch.events if event['phase'] == 'testing']
                assert len(times) >= 2, watch.events
                return min(times), max(times)

            a, b = interval(cp_watch), interval(exam_watch)
            overlap = min(a[1], b[1]) - max(a[0], b[0])
            assert overlap > 0, (a, b)
            with sqlite3.connect(folder / 'executor.sqlite') as db:
                assert db.execute("SELECT COUNT(*) FROM execution_jobs WHERE delivered_at IS NULL OR (result IS NOT NULL AND result != 'null')").fetchone()[0] == 0
            # Admin browser login is bound to its own browser state and CP role.
            call(student,'GET',urls['cp']+'/insider/settings/code-executor/status',403)
            admin=cp_client('admin')
            status=call(admin,'GET',urls['cp']+'/insider/settings/code-executor/status').json()
            assert any(worker['concurrency']==2 for worker in status['workers']),status
            console=requests.Session()
            redirect=call(console,'GET',urls['executor']+'/auth/login',302,allow_redirects=False)
            verified=call(admin,'GET',redirect.headers['Location'],302,allow_redirects=False)
            token=parse_qs(urlsplit(verified.headers['Location']).fragment)['token'][0]
            call(console,'POST',urls['executor']+'/auth/callback',json={'token':token})
            call(console,'GET',urls['executor']+'/system/status')
            summary = dict(status='passed', duration_seconds=round(time.monotonic() - started, 2),
                parallel_testing_overlap_seconds=round(overlap, 2), ai_provider='local Responses stub',
                phases={name: [event['phase'] for _, event in watch.events] for name, watch in [
                    ('codingprojects', cp_watch), ('geekexam', exam_watch), ('cpp', cpp_watch), ('ai', ai_watch)]})
            (args.output / 'result.json').write_text(json.dumps(summary, ensure_ascii=False, indent=2))
            print(json.dumps(summary, ensure_ascii=False, indent=2), flush=True)
    finally:
        for watch in watches:
            watch.close()
        for process in reversed(processes):
            process.terminate()
            try:
                process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                process.kill()
                process.wait()
        for name, log in logs:
            try:
                log.flush()
                log.seek(0)
                (args.output / (name + '.log')).write_text(log.read())
            finally:
                log.close()
        subprocess.run(docker + ['rm', '-f', container], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        ai.shutdown()


if __name__ == '__main__':
    main()
