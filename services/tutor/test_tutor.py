import json
import unittest
from unittest.mock import Mock, patch

import requests

import tutor


class TutorTests(unittest.TestCase):
    def setUp(self):
        self.data = {"mode":"hint", "topic":"Pillow", "question":"Как проверить цвет?", "assignment":"Фильтр",
                     "history":[], "excerpt":"", "review":None,
                     "readings":[{"id":"R1","title":"Изображения","heading":"Цвет","text":"Три цветовых канала."}]}

    def test_service_requires_its_own_token_and_works_offline(self):
        with patch.dict("os.environ", {"TUTOR_SERVICE_SECRET":"test", "OPENAI_API_KEY":""}):
            client = tutor.app.test_client()
            denied = client.post("/v1/tutor/replies", json=self.data)
            self.assertEqual(denied.status_code, 401)
            self.assertEqual(denied.json["usage"]["total_tokens"], 0)
            invalid = client.post("/v1/tutor/replies", headers={"Authorization":"Bearer test"}, json={})
            self.assertEqual(invalid.status_code, 422)
            self.assertEqual(invalid.json["usage"]["calls"], [])
            res = client.post("/v1/tutor/replies", headers={"Authorization":"Bearer test"}, json=self.data)
            self.assertEqual(res.json["sources"], ["R1"])
            self.assertTrue(res.json["guided"])
            self.assertEqual(res.json["usage"], {"known_tokens":0, "total_tokens":0, "input_tokens":0,
                             "output_tokens":0, "calls":[], "complete":True})

    @patch.dict("os.environ", {"OPENAI_API_KEY":"dummy"})
    def test_unknown_citation_and_answer_rejected_by_checker_both_fall_back(self):
        for ids in [["missing"], ["R1"]]:
            draft = {"answer":"Неверная подсказка", "sources":ids, "card":None}
            response = tutor.compose(self.data, lambda prompt, data: draft if prompt == tutor.PROMPT else {"allow":False})
            self.assertTrue(response["guided"])

    @patch.dict("os.environ", {"OPENAI_API_KEY":"dummy"})
    def test_approved_card_is_resolved_from_local_catalog(self):
        draft = {"answer":"Проверь один пиксель.", "sources":["R1"], "card":"pillow.pixel"}
        response = tutor.compose(self.data, lambda prompt, data: draft if prompt == tutor.PROMPT else {"allow":True})
        self.assertFalse(response["guided"])
        self.assertEqual(response["reference"]["example"], "img.getpixel((0, 0))")

    def test_recheck_needs_a_specific_verified_finding_in_the_selected_submission(self):
        data = {**self.data, "mode":"review", "review":{"comment":"Ноль пропущен"},
                "latest_submission":{"text":"if n < 0:", "files":[], "source_status":"ready", "result":{}},
                "review_basis":{"feedback":["Ноль пропущен"], "source":{"text":"if n <= 0:", "files":[], "source_status":"ready"}}}
        finding = {"claim":"Ноль пропущен", "evidence":"if n <= 0:", "focus":"Сопоставить выбор ветки при нуле с замечанием."}
        def check(signal, confirmation, payload=data):
            draft = {"answer":"Сравни выбор ветки для нуля.", "sources":[], "card":None, "recheck":signal}
            return tutor.compose(payload, lambda stage, _: draft if stage == tutor.PROMPT else confirmation)["recheck"]
        approve = {"allow":True, "confirm_recheck":True}
        self.assertEqual(check(finding, approve), finding)
        for change in ({"claim":"Цикл неверен"}, {"evidence":"if n < 0:"}, {"focus":""}):
            self.assertIsNone(check({**finding, **change}, approve))
        for confirmation in ({"allow":True}, {"allow":True, "confirm_recheck":False}, {"allow":False, "confirm_recheck":True}):
            self.assertIsNone(check(finding, confirmation))
        self.assertIsNone(check(finding, approve, {**data, "mode":"hint"}))
        data["review_basis"]["source"]["source_status"] = "partial"
        self.assertIsNone(check(finding, approve))
        self.assertIsNone(tutor.compose(data, None)["recheck"])

    @staticmethod
    def provider_reply(content, input_tokens=100, output_tokens=10, status="completed"):
        response = Mock(headers={"x-request-id":"provider-123"})
        response.json.return_value = {
            "status":status, "model":"example-snapshot",
            "output":[{"type":"message", "content":[{"type":"output_text", "text":json.dumps(content)}]}],
            "usage":{"input_tokens":input_tokens, "output_tokens":output_tokens, "total_tokens":input_tokens + output_tokens,
                     "input_tokens_details":{"cached_tokens":20}, "output_tokens_details":{"reasoning_tokens":2}},
        }
        return response

    @patch.dict("os.environ", {"OPENAI_API_KEY":"dummy"})
    @patch("tutor.requests.post")
    def test_both_calls_are_charged_even_for_a_rejected_answer(self, post):
        draft = {"answer":"Проверь один пиксель.", "sources":["R1"], "card":None}
        post.side_effect = [self.provider_reply(draft), self.provider_reply({"allow":False}, 60, 5)]
        result = tutor.reply(self.data)
        usage = result["usage"]
        self.assertTrue(result["guided"])
        self.assertTrue(usage["complete"])
        self.assertEqual((usage["input_tokens"], usage["output_tokens"], usage["total_tokens"]), (160, 15, 175))
        self.assertEqual([c["stage"] for c in usage["calls"]], ["answer", "check"])
        self.assertEqual(usage["calls"][1]["reasoning_tokens"], 2)
        self.assertEqual(usage["calls"][0]["provider_request_id"], "provider-123")
        self.assertEqual(usage["calls"][0]["cached_input_tokens"], 20)

    @patch("tutor.requests.post")
    def test_basic_help_defaults_to_terra_medium_for_both_calls(self, post):
        for mode in ("theory", "hint"):
            for settings in ({}, {"GPT_MODEL":"", "TUTOR_CHECK_MODEL":"", "TUTOR_REASONING_EFFORT":""}):
                with self.subTest(mode=mode, settings=settings), patch.dict(
                        "os.environ", {"OPENAI_API_KEY":"dummy", **settings}, clear=True):
                    post.reset_mock()
                    post.side_effect = [self.provider_reply({"answer":"Проверь одну точку.", "sources":[], "card":None}),
                                        self.provider_reply({"allow":True})]
                    result = tutor.reply({**self.data, "mode":mode})
                    calls = [call.kwargs["json"] for call in post.call_args_list]
                    self.assertEqual([(c["model"], c["reasoning"]["effort"]) for c in calls],
                                     [("gpt-6.1-terra", "medium")] * 2)
                    self.assertEqual([c["reasoning_effort"] for c in result["usage"]["calls"]], ["medium"] * 2)

    @patch.dict("os.environ", {"OPENAI_API_KEY":"dummy", "GPT_MODEL":"basic-answer", "TUTOR_CHECK_MODEL":"basic-check",
                               "TUTOR_REASONING_EFFORT":"low", "TUTOR_REVIEW_MODEL":"", "TUTOR_REVIEW_EFFORT":""})
    @patch("tutor.requests.post")
    def test_routing_depends_on_the_requested_help_even_with_an_existing_submission(self, post):
        work = {"text":"draft", "files":[], "result":{"method":"tests","passed":0,"total":1}}
        for mode in ("submission", "review", "hint", "theory"):
            with self.subTest(mode=mode):
                data = {**self.data, "mode":mode, "latest_submission":None if mode == "theory" else work,
                        "review":{"comment":"Нарушен порядок."} if mode == "review" else None}
                self.assertTrue(tutor.valid(data))
                post.reset_mock()
                post.side_effect = [self.provider_reply({"answer":"Сравни два числа.", "sources":[], "card":None}),
                                    self.provider_reply({"allow":True})]
                reply = tutor.reply(data)
                calls = [call.kwargs["json"] for call in post.call_args_list]
                models = ["gpt-6.1-terra"] * 2 if mode in ("submission", "review") else ["basic-answer","basic-check"]
                effort = "medium" if mode in ("submission", "review") else "low"
                self.assertEqual([c["model"] for c in calls], models)
                self.assertEqual([c["reasoning"]["effort"] for c in calls], [effort] * 2)
                self.assertEqual([c["reasoning_effort"] for c in reply["usage"]["calls"]], [effort] * 2)
                self.assertEqual(reply["usage"]["total_tokens"], 220)
        self.assertFalse(tutor.valid({**self.data, "mode":"submission"}))

    @patch.dict("os.environ", {"OPENAI_API_KEY":"dummy"})
    @patch("tutor.requests.post")
    def test_timeout_in_checker_preserves_known_usage(self, post):
        draft = {"answer":"Проверь один пиксель.", "sources":["R1"], "card":None}
        post.side_effect = [self.provider_reply(draft), requests.Timeout()]
        result = tutor.reply(self.data)
        self.assertTrue(result["guided"])
        self.assertEqual(result["usage"]["known_tokens"], 110)
        self.assertIsNone(result["usage"]["total_tokens"])
        self.assertFalse(result["usage"]["complete"])
        self.assertIsNone(result["usage"]["calls"][1]["total_tokens"])

    @patch.dict("os.environ", {"OPENAI_API_KEY":"dummy"})
    @patch("tutor.requests.post")
    def test_incomplete_provider_output_can_still_have_exact_cost(self, post):
        post.return_value = self.provider_reply(None, status="incomplete")
        result = tutor.reply(self.data)
        self.assertTrue(result["guided"])
        self.assertTrue(result["usage"]["complete"])
        self.assertEqual(result["usage"]["known_tokens"], 110)
        self.assertEqual(post.call_count, 1)

    @patch.dict("os.environ", {"OPENAI_API_KEY":"dummy"})
    def test_plaintext_program_is_replaced_even_if_a_model_would_approve(self):
        generate = Mock(return_value={"answer":"x = int(input())\nprint(x * 2)", "sources":["R1"], "card":None})
        self.assertTrue(tutor.compose(self.data, generate)["guided"])
        self.assertEqual(generate.call_count, 1)

    @patch.dict("os.environ", {"TUTOR_SERVICE_SECRET":"test", "OPENAI_API_KEY":"dummy", "TUTOR_REASONING_EFFORT":"medium"})
    @patch("tutor.requests.post")
    def test_full_context_reaches_both_calls_and_a_whole_step_can_be_cited(self, post):
        self.data.update(assignment="Полное условие. " * 750,
            lesson={"title":"Изображения","steps":[{"id":"S1","title":"Пиксели","current":True,"text":"Пример. " * 15000}]},
            neighbors=[{"id":"S2","title":"Цвет","lesson":"Каналы","side":"previous","text":"Три канала."}],
            latest_submission={"text":"","language":"python","mode":"code","source_status":"ready",
                "files":[{"name":"main.py","kind":"text","text":"x = 3\n" * 1400}],"result":{"state":"completed","method":"gpt"}},
            previous_results=[{"method":"tests","passed":1,"total":3}])
        post.side_effect = [self.provider_reply({"answer":"Посмотри на цвет одной точки.", "sources":["S1"], "card":None}),
                            self.provider_reply({"allow":True})]
        result = tutor.app.test_client().post("/v1/tutor/replies", headers={"Authorization":"Bearer test"}, json=self.data)
        self.assertEqual(result.status_code, 200)
        self.assertEqual(result.json["sources"], ["S1"])
        self.assertEqual(result.json["usage"]["total_tokens"], 220)
        for call in post.call_args_list:
            self.assertEqual(call.kwargs["json"]["reasoning"], {"effort":"medium"})
            model_input = json.loads(call.kwargs["json"]["input"])
            for key in ("assignment","lesson","neighbors","latest_submission","previous_results"):
                self.assertEqual(model_input[key], self.data[key])

    @patch.dict("os.environ", {"TUTOR_SERVICE_SECRET":"test"})
    def test_oversize_context_returns_zero_usage_and_malformed_sources_are_rejected(self):
        client = tutor.app.test_client()
        self.data["assignment"] = "x" * tutor.app.config["MAX_CONTENT_LENGTH"]
        result = client.post("/v1/tutor/replies", headers={"Authorization":"Bearer test"}, json=self.data)
        self.assertEqual(result.status_code, 413)
        self.assertEqual(result.json["usage"]["total_tokens"], 0)
        self.data.update(assignment="Текст", lesson=None)
        self.assertFalse(tutor.valid(self.data))

    def test_score_alone_is_not_an_explanation(self):
        self.data.update(mode="review", review={"comment":"", "points":7})
        self.assertIn("нет пояснения", tutor.reply(self.data)["answer"])

